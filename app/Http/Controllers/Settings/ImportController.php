<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Imports\CompanyImport;
use App\Imports\ContactImport;
use App\Imports\EquipmentImport;
use App\Imports\Importer;
use App\Models\AuditLog;
use App\Models\Company;
use App\Services\Vies;
use App\Support\SheetReader;
use App\Support\VatNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Settings › Import data. Upload a spreadsheet, see exactly what would
 * happen (a dry run that is rolled back), then import for real.
 */
class ImportController extends Controller
{
    /** Import kinds in the order they run (companies first, so contacts can link). */
    public const KINDS = ['companies' => CompanyImport::class, 'contacts' => ContactImport::class, 'equipment' => EquipmentImport::class];

    public function index(): View
    {
        return view('settings.import', [
            'viesPending' => $this->viesPendingQuery()->count(),
        ]);
    }

    public function template(): BinaryFileResponse
    {
        return response()->download(resource_path('templates/CLUK_Import_Templates.xlsx'), 'CLUK_Import_Templates.xlsx');
    }

    /** Step 1: upload and dry run. */
    public function preview(Request $request): View|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:xlsx,csv,txt'],
            'kind' => ['nullable', 'in:auto,companies,contacts,equipment'],
        ]);

        $file = $request->file('file');
        $token = Str::uuid()->toString();
        $extension = strtolower($file->getClientOriginalExtension());
        Storage::disk('local')->putFileAs('imports', $file, $token.'.'.$extension);

        $sheets = $this->sheets($token, $extension, $request->input('kind', 'auto'));
        $request->session()->put('import.'.$token, ['ext' => $extension, 'kind' => $request->input('kind', 'auto'), 'name' => $file->getClientOriginalName()]);

        DB::beginTransaction();
        try {
            $reports = $this->runAll($sheets);
        } finally {
            DB::rollBack();
        }

        return view('settings.import', [
            'viesPending' => $this->viesPendingQuery()->count(),
            'token' => $token,
            'fileName' => $file->getClientOriginalName(),
            'reports' => $reports,
            'dryRun' => true,
        ]);
    }

    /** Step 2: the real import of the file that was previewed. */
    public function run(Request $request): View|RedirectResponse
    {
        $token = (string) $request->input('token');
        $meta = $request->session()->pull('import.'.$token);
        abort_if($meta === null || ! Str::isUuid($token), 404);

        $sheets = $this->sheets($token, $meta['ext'], $meta['kind']);
        $reports = DB::transaction(fn () => $this->runAll($sheets));
        Storage::disk('local')->delete('imports/'.$token.'.'.$meta['ext']);

        AuditLog::record('import.done', null, ['file' => $meta['name']] + array_map(fn ($r) => array_intersect_key($r, array_flip(['created', 'updated', 'skipped'])), $reports));

        return view('settings.import', [
            'viesPending' => $this->viesPendingQuery()->count(),
            'fileName' => $meta['name'],
            'reports' => $reports,
            'dryRun' => false,
        ]);
    }

    /** Check imported EU VAT numbers in VIES, a batch at a time. */
    public function vies(Vies $vies): RedirectResponse
    {
        $checked = 0;
        foreach ($this->viesPendingQuery()->limit(15)->get() as $company) {
            $result = $vies->check($company->vat_number);
            $company->forceFill([
                'vies_status' => $result['status'], 'vies_checked_at' => now(),
                'vies_name' => $result['name'], 'vies_address' => $result['address'],
            ])->save();
            $checked++;
        }

        return redirect()->route('settings.import.index')
            ->with('status', __('import.flash.vies_checked', ['count' => $checked, 'left' => $this->viesPendingQuery()->count()]));
    }

    /** @return array<string, list<list<string|null>>> kind => rows */
    private function sheets(string $token, string $extension, string $kind): array
    {
        $path = Storage::disk('local')->path('imports/'.$token.'.'.$extension);

        try {
            $all = SheetReader::read($path, $extension);
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['file' => __('import.errors.unreadable')]);
        }

        $found = [];
        foreach (self::KINDS as $key => $class) {
            if ($kind !== 'auto') {
                if ($kind === $key) {
                    $found[$key] = $this->pickSheet($all, $class) ?? reset($all);
                }

                continue;
            }
            if (($rows = $this->pickSheet($all, $class)) !== null) {
                $found[$key] = $rows;
            }
        }

        // One sheet without a known name (e.g. a CSV): guess from the columns.
        if ($found === [] && $kind === 'auto' && count($all) >= 1) {
            $rows = reset($all);
            $header = array_map(fn ($h) => Importer::key((string) $h), $rows[0] ?? []);
            $found[array_intersect($header, ['firstname', 'voornaam', 'prenom']) ? 'contacts'
                : (array_intersect($header, ['itemnumber', 'serialnumber', 'artikelnummer', 'catalogprice']) ? 'equipment' : 'companies')] = $rows;
        }

        if ($found === []) {
            throw ValidationException::withMessages(['file' => __('import.errors.no_sheet')]);
        }

        return $found;
    }

    /** @param array<string, list<list<string|null>>> $all */
    private function pickSheet(array $all, string $class): ?array
    {
        foreach ($all as $name => $rows) {
            if (in_array(Importer::key($name), $class::sheetNames(), true)) {
                return $rows;
            }
        }

        return null;
    }

    /** @param array<string, list<list<string|null>>> $sheets */
    private function runAll(array $sheets): array
    {
        $reports = [];
        foreach (self::KINDS as $key => $class) {
            if (isset($sheets[$key])) {
                $reports[$key] = (new $class)->run($sheets[$key]);
            }
        }

        return $reports;
    }

    private function viesPendingQuery()
    {
        $prefixes = array_map(fn ($c) => VatNumber::prefix($c), VatNumber::EU);

        return Company::query()->active()->whereNotNull('vat_number')->whereNull('vies_checked_at')
            ->where(fn ($q) => collect($prefixes)->each(fn ($p) => $q->orWhere('vat_number', 'like', $p.'%')));
    }
}
