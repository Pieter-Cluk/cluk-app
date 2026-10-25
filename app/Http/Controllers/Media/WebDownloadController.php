<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Website\Download;
use App\Models\Website\DownloadLog;
use App\Services\Media\Translator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Media › Website › Downloads: documents for the website's downloads page, and who downloaded them. */
class WebDownloadController extends Controller
{
    public const MIMES = 'pdf,doc,docx,xls,xlsx,ppt,pptx,zip,jpg,jpeg,png,dwg,dxf,txt,csv';

    public const MAX_KB = 51200; // 50 MB

    public function store(Request $request): RedirectResponse
    {
        $this->can($request);
        $d = $this->validated($request, true);
        $file = $request->file('file');
        $doc = Download::create([
            'path' => $file->storeAs('web-downloads', Str::random(32).'.'.strtolower($file->getClientOriginalExtension()), 'local'),
            'original_name' => $this->fileName($file->getClientOriginalName()), 'mime' => $file->getMimeType(), 'size' => $file->getSize(),
            'sort' => (int) Download::max('sort') + 1, 'created_by_id' => $request->user()->id,
        ] + $this->fields($request, $d));
        AuditLog::record('website.download_added', $doc, ['title' => $doc->label('en', 'title'), 'access' => $doc->access], $request->user());

        return $this->back(__('website.downloads.flash.added'));
    }

    public function update(Request $request, Download $download): RedirectResponse
    {
        $this->can($request);
        $d = $this->validated($request, false);
        $values = $this->fields($request, $d);
        if ($file = $request->file('file')) {
            Storage::disk('local')->delete($download->path);
            $values += ['path' => $file->storeAs('web-downloads', Str::random(32).'.'.strtolower($file->getClientOriginalExtension()), 'local'),
                'original_name' => $this->fileName($file->getClientOriginalName()), 'mime' => $file->getMimeType(), 'size' => $file->getSize()];
        }
        $download->update($values);
        if ($request->filled('place')) {
            $this->placeAt($download, (int) $request->input('place'));
        }
        AuditLog::record('website.download_changed', $download, ['title' => $download->label('en', 'title'), 'access' => $download->access, 'active' => $download->active, 'new_file' => (bool) $file], $request->user());

        return $this->back(__('website.flash.saved'));
    }

    public function move(Request $request, Download $download, string $dir): RedirectResponse
    {
        $this->can($request);
        $list = Download::orderBy('sort')->orderBy('id')->get()->values();
        $i = $list->search(fn ($x) => $x->id === $download->id);
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($j >= 0 && $j < $list->count()) {
            [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
        }
        foreach ($list as $n => $x) {
            $x->forceFill(['sort' => $n])->save();
        }

        return $this->back(null);
    }

    /** Put a document at a place in the order (1 = first), the others shift. */
    private function placeAt(Download $download, int $place): void
    {
        $list = Download::where('id', '!=', $download->id)->orderBy('sort')->orderBy('id')->get()->values();
        $list->splice(max(0, min($place - 1, $list->count())), 0, [$download]);
        foreach ($list as $n => $x) {
            $x->forceFill(['sort' => $n])->save();
        }
    }

    public function destroy(Request $request, Download $download): RedirectResponse
    {
        $this->can($request);
        AuditLog::record('website.download_removed', null, ['title' => $download->label('en', 'title'), 'downloads' => $download->downloads], $request->user());
        Storage::disk('local')->delete($download->path);
        $download->delete();

        return $this->back(__('website.downloads.flash.removed'));
    }

    /** Who downloaded: one document, or all. */
    public function log(Request $request): View
    {
        $doc = $request->integer('doc') ? Download::find($request->integer('doc')) : null;
        $known = $request->query('who') === 'known';

        return view('media.website.downloads-log', [
            'doc' => $doc, 'known' => $known, 'docs' => Download::orderBy('sort')->get(),
            'logs' => DownloadLog::with(['download', 'company', 'contact', 'account'])
                ->when($doc, fn ($q) => $q->where('download_id', $doc->id))
                ->when($known, fn ($q) => $q->where(fn ($w) => $w->whereNotNull('company_id')->orWhereNotNull('contact_id')->orWhereNotNull('customer_account_id')))
                ->latest('id')->paginate(50)->withQueryString(),
        ]);
    }

    private function validated(Request $request, bool $new): array
    {
        return $request->validate([
            'file' => [$new ? 'required' : 'nullable', 'file', 'mimes:'.self::MIMES, 'max:'.self::MAX_KB],
            'title' => ['required', 'array'], 'title.en' => ['required', 'string', 'max:160'], 'title.*' => ['nullable', 'string', 'max:160'],
            'description' => ['array'], 'description.*' => ['nullable', 'string', 'max:400'],
            'access' => ['required', Rule::in(Download::ACCESS)],
            'category' => ['required', Rule::in(config('website.download_categories'))],
            'locale' => ['nullable', Rule::in(array_keys(config('cluk.locales')))],
            'active' => ['boolean'],
            'place' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);
    }

    private function fields(Request $request, array $d): array
    {
        $locales = array_keys(config('cluk.locales'));
        $clean = fn ($a) => array_filter(array_map(fn ($v) => trim((string) $v), array_intersect_key($a ?? [], array_flip($locales))), fn ($v) => $v !== '');
        $title = $clean($d['title']);
        $desc = $clean($d['description'] ?? []);
        if ($request->boolean('translate') && Translator::available()) {
            $tr = app(Translator::class)->translate(array_filter(['title' => $title['en'] ?? null, 'description' => $desc['en'] ?? null])) ?? [];
            foreach ($tr as $loc => $f) {
                if (! empty($f['title'])) {
                    $title[$loc] ??= $f['title'];
                }
                if (! empty($f['description'])) {
                    $desc[$loc] ??= $f['description'];
                }
            }
        }

        return ['title' => $title, 'description' => $desc ?: null, 'access' => $d['access'], 'category' => $d['category'], 'locale' => $d['locale'] ?? null, 'active' => $request->boolean('active', true)];
    }

    /** The name the visitor's file gets: as uploaded, without folders. */
    private function fileName(string $name): string
    {
        $name = trim(str_replace(['/', '\\', '"'], ' ', basename($name)));

        return Str::limit($name !== '' ? $name : 'document', 190, '');
    }

    private function can(Request $request): void
    {
        abort_unless($request->user()->hasRight('website.edit'), 403);
    }

    private function back(?string $msg): RedirectResponse
    {
        $r = redirect()->to(route('website.index', ['tab' => 'downloads']).'#downloads');

        return $msg ? $r->with('status', $msg) : $r;
    }
}
