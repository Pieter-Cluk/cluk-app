<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use App\Models\VatRate;
use App\Services\Vies;
use App\Support\Images;
use App\Support\VatNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Companies & contacts: customers, suppliers and the people there. */
class CompanyController extends Controller
{
    public const VIEWS = ['all', 'customers', 'suppliers', 'both', 'contacts'];

    public function __construct(private readonly Vies $vies) {}

    public function index(Request $request): View
    {
        $view = in_array($request->query('view'), self::VIEWS, true) ? $request->query('view') : 'all';
        $q = trim((string) $request->query('q', ''));
        $filters = [
            'country' => strtoupper((string) $request->query('country', '')),
            'owner' => (string) $request->query('owner', ''),
            'rel' => (string) $request->query('rel', ''),
            'supplies' => (string) $request->query('supplies', ''),
        ];

        $base = Company::query()->active();
        $counts = [
            'all' => (clone $base)->count(),
            'customers' => (clone $base)->where('is_customer', true)->count(),
            'suppliers' => (clone $base)->where('is_supplier', true)->count(),
            'both' => (clone $base)->where('is_customer', true)->where('is_supplier', true)->count(),
            'contacts' => Contact::count(),
        ];

        $companies = null;
        $contacts = null;

        if ($view === 'contacts') {
            $contacts = Contact::query()
                ->with('companies')
                ->when($q !== '', function ($query) use ($q) {
                    $like = $this->like($q);
                    $query->where(fn ($w) => $w
                        ->whereRaw('lower(first_name) like ?', [$like])
                        ->orWhereRaw('lower(coalesce(last_name, \'\')) like ?', [$like])
                        ->orWhereRaw('lower(coalesce(email, \'\')) like ?', [$like])
                        ->orWhereHas('companies', fn ($c) => $c->whereRaw('lower(name) like ?', [$like])));
                })
                ->when($filters['country'] !== '', fn ($query) => $query->whereHas('companies', fn ($c) => $c->where('country', $filters['country'])))
                ->orderBy('first_name')->orderBy('last_name')
                ->paginate(50)->withQueryString();
        } else {
            $companies = Company::query()
                ->active()
                ->with('accountOwner')
                ->withCount('contacts')
                ->relationship($view)
                ->when($q !== '', function ($query) use ($q) {
                    $like = $this->like($q);
                    $vat = '%'.strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $q)).'%';
                    $query->where(fn ($w) => $w
                        ->whereRaw('lower(name) like ?', [$like])
                        ->orWhereRaw('lower(coalesce(city, \'\')) like ?', [$like])
                        ->orWhere('vat_number', 'like', $vat));
                })
                ->when($filters['country'] !== '', fn ($query) => $query->where('country', $filters['country']))
                ->when($filters['owner'] !== '', fn ($query) => $query->where('account_owner_id', (int) $filters['owner']))
                ->when($view === 'all' && in_array($filters['rel'], ['customer', 'supplier', 'both'], true), fn ($query) => $query
                    ->where('is_customer', $filters['rel'] !== 'supplier')
                    ->where('is_supplier', $filters['rel'] !== 'customer'))
                ->when($view === 'suppliers' && in_array($filters['supplies'], Company::SUPPLIES, true), fn ($query) => $query->where('supplies', 'like', '%"'.$filters['supplies'].'"%'))
                ->orderBy('name')
                ->paginate(50)->withQueryString();
        }

        return view('companies.index', [
            'view' => $view,
            'q' => $q,
            'filters' => $filters,
            'counts' => $counts,
            'companies' => $companies,
            'contacts' => $contacts,
            'countries' => Company::query()->active()->distinct()->orderBy('country')->pluck('country'),
            'owners' => User::whereIn('id', Company::query()->whereNotNull('account_owner_id')->distinct()->pluck('account_owner_id'))->orderBy('first_name')->get(),
            'canEdit' => $request->user()->canUseModule('companies', 2),
        ]);
    }

    public function create(Request $request): View
    {
        $company = new Company([
            'is_customer' => $request->query('type') !== 'supplier',
            'is_supplier' => $request->query('type') === 'supplier',
            'country' => 'BE', 'language' => 'nl', 'vat_regime' => 'auto',
            'account_owner_id' => $request->user()->is_commercial ? $request->user()->id : null,
        ]);

        return view('companies.form', ['company' => $company, 'owners' => $this->owners($company)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $company = Company::create($data);
        $this->saveLogo($request, $company);
        AuditLog::record('crm.company_created', $company, ['name' => $company->name]);

        $this->checkVies($company);

        return redirect()->route('companies.show', $company)->with('status', __('companies.flash.created', ['name' => $company->name]));
    }

    public function show(Request $request, Company $company): View
    {
        $company->load(['accountOwner', 'contacts' => fn ($q) => $q->with('companies')->orderByDesc('company_contact.is_main')->orderBy('first_name'), 'addresses']);

        return view('companies.show', [
            'company' => $company,
            'vatRate' => $company->vatRate(),
            'vatChoices' => VatRate::activeChoices(),
            'canEdit' => $request->user()->canUseModule('companies', 2),
            'canArchive' => $request->user()->canUseModule('companies', 3),
            'canVat' => $request->user()->hasRight('finance.vat_treatment'),
        ]);
    }

    public function edit(Company $company): View
    {
        return view('companies.form', ['company' => $company, 'owners' => $this->owners($company)]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $data = $this->validated($request, $company);
        $vatChanged = ($data['vat_number'] ?? null) !== $company->vat_number || $data['country'] !== $company->country;

        $company->fill($data);
        $changes = array_keys($company->getDirty());
        $company->save();
        $this->saveLogo($request, $company);
        if ($changes !== []) {
            AuditLog::record('crm.company_updated', $company, ['fields' => $changes]);
        }

        if ($vatChanged) {
            $company->forceFill(['vies_status' => null, 'vies_checked_at' => null, 'vies_name' => null, 'vies_address' => null])->save();
            $this->checkVies($company);
        }

        return redirect()->route('companies.show', $company)->with('status', __('companies.flash.saved'));
    }

    /** "Check again" on the company card. */
    public function vies(Company $company): RedirectResponse
    {
        $result = $this->checkVies($company);

        return redirect()->route('companies.show', $company)
            ->with('status', __('companies.vies.result_'.($result ?? 'not_eu')));
    }

    /** VAT treatment: automatic, or one VAT code chosen by Finance. */
    /** A logo chosen in the company form (the company page has its own upload too). */
    private function saveLogo(Request $request, Company $company): void
    {
        if (! $request->hasFile('logo')) {
            return;
        }
        $request->validate(['logo' => Images::rule()]);
        try {
            $path = Images::store($request->file('logo'), 'logos', 400, true);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['logo' => __('pictures.not_image')]);
        }
        Images::delete($company->logo_path);
        $company->forceFill(['logo_path' => $path])->save();
    }

    /** Internal notes, straight from the company page. */
    public function notes(Request $request, Company $company): RedirectResponse
    {
        $company->update(['notes' => $request->validate(['notes' => ['nullable', 'string', 'max:5000']])['notes'] ?? null]);
        AuditLog::record('crm.company_updated', $company, ['fields' => ['notes']]);

        return redirect()->route('companies.show', $company)->with('status', __('companies.flash.notes_saved'));
    }

    public function updateVat(Request $request, Company $company): RedirectResponse
    {
        $codes = VatRate::activeChoices()->pluck('code')->all();
        $data = $request->validate(['vat_regime' => ['required', Rule::in(array_merge(['auto'], $codes))]]);

        if ($data['vat_regime'] !== $company->vat_regime) {
            $from = $company->vat_regime;
            $company->update(['vat_regime' => $data['vat_regime']]);
            AuditLog::record('crm.vat_regime_changed', $company, ['from' => $from, 'to' => $data['vat_regime'], 'code' => $company->vatCode()]);
        }

        return redirect()->route('companies.show', $company)->with('status', __('companies.flash.vat_saved'));
    }

    public function archive(Company $company): RedirectResponse
    {
        $company->forceFill(['archived_at' => now()])->save();
        AuditLog::record('crm.company_archived', $company, ['name' => $company->name]);

        return redirect()->route('companies.index')->with('status', __('companies.flash.archived', ['name' => $company->name]));
    }

    public function restore(Company $company): RedirectResponse
    {
        $company->forceFill(['archived_at' => null])->save();
        AuditLog::record('crm.company_restored', $company, ['name' => $company->name]);

        return redirect()->route('companies.show', $company)->with('status', __('companies.flash.restored'));
    }

    /** Ask VIES and remember the answer. Returns the status, or null when VIES does not apply. */
    private function checkVies(Company $company): ?string
    {
        if (! Vies::appliesTo($company->vat_number)) {
            return null;
        }

        $result = $this->vies->check($company->vat_number);
        $company->forceFill([
            'vies_status' => $result['status'],
            'vies_checked_at' => now(),
            'vies_name' => $result['name'] ?? ($result['status'] === 'unavailable' ? $company->vies_name : null),
            'vies_address' => $result['address'] ?? ($result['status'] === 'unavailable' ? $company->vies_address : null),
        ])->save();

        return $result['status'];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Company $company = null): array
    {
        $country = strtoupper((string) $request->input('country', 'BE'));
        $request->merge([
            'country' => $country,
            'vat_number' => VatNumber::normalize($request->input('vat_number'), $country),
            'email' => $request->filled('email') ? mb_strtolower(trim((string) $request->input('email'))) : null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'legal_form' => ['nullable', 'string', 'max:20'],
            'is_customer' => ['boolean'],
            'is_supplier' => ['boolean'],
            'vat_number' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'street' => ['nullable', 'string', 'max:160'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'website' => ['nullable', 'string', 'max:190'],
            'language' => ['required', Rule::in(array_keys(config('cluk.locales')))],
            'account_owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer_payment_days' => ['nullable', 'integer', 'between:0,365'],
            'supplier_payment_days' => ['nullable', 'integer', 'between:0,365'],
            'supplies' => ['array'],
            'supplies.*' => [Rule::in(Company::SUPPLIES)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $data['is_customer'] = $request->boolean('is_customer');
        $data['is_supplier'] = $request->boolean('is_supplier');
        $data['po_required'] = $request->boolean('po_required');
        $data['supplies'] = $data['is_supplier'] ? array_values($data['supplies'] ?? []) : [];
        $data['account_owner_id'] = isset($data['account_owner_id']) ? (int) $data['account_owner_id'] : null;

        $errors = [];
        if (! $data['is_customer'] && ! $data['is_supplier']) {
            $errors['is_customer'] = __('companies.errors.no_relationship');
        }
        if ($data['vat_number'] !== null && ! VatNumber::looksValid($data['vat_number'])) {
            $errors['vat_number'] = __('companies.errors.vat_format', ['number' => VatNumber::format($data['vat_number'])]);
        }
        if ($data['vat_number'] !== null && ($twin = Company::active()->where('vat_number', $data['vat_number'])->when($company, fn ($q) => $q->whereKeyNot($company->id))->first())) {
            $errors['vat_number'] = __('companies.errors.vat_exists', ['name' => $twin->name]);
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    /** People who can own a customer: commercial colleagues, plus whoever owns it now. */
    private function owners(Company $company): Collection
    {
        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->where(fn ($q) => $q->where('is_commercial', true)->when($company->account_owner_id, fn ($w) => $w->orWhere('id', $company->account_owner_id)))
            ->orderBy('first_name')->get();
    }

    private function like(string $q): string
    {
        return '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtolower($q)).'%';
    }
}
