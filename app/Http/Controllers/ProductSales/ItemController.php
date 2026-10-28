<?php

namespace App\Http\Controllers\ProductSales;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Product\Brand;
use App\Models\ProductSales\ItemDocument;
use App\Models\ProductSales\SalesItem;
use App\Notifications\Confirmation;
use App\Services\Products\Classification;
use App\Support\Images;
use App\Support\InLocale;
use App\Support\SafeMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Product sales › Items: what we sell, its suppliers with their discount and lead time, and its PDFs. */
class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $cat = (int) $request->query('cat', 0) ?: null;
        $brand = (int) $request->query('brand', 0) ?: null;
        $q = trim((string) $request->query('q'));
        $web = in_array($request->query('web'), ['online', 'partial', 'off'], true) ? $request->query('web') : null;
        $items = SalesItem::with(['suppliers', 'productCategory', 'productSubcategory'])->where('active', true)
            ->when($web === 'off', fn ($w) => config('website.only_ticked') ? $w->where('on_website', false) : $w->whereRaw('1 = 0'))
            ->when($web && $web !== 'off', fn ($w) => $w->when(config('website.only_ticked'), fn ($x) => $x->where('on_website', true))
                ->where(fn ($x) => $web === 'partial'
                    ? $x->whereNull('photo_path')->orWhere('list_price_cents', '<=', 0)->orWhereNull('subcategory_id')
                        ->orWhereHas('productSubcategory', fn ($y) => $y->where('active', false))->orWhereHas('productCategory', fn ($y) => $y->where('active', false))
                    : $x->whereNotNull('photo_path')->where('list_price_cents', '>', 0)->whereHas('productSubcategory', fn ($y) => $y->where('active', true))->whereHas('productCategory', fn ($y) => $y->where('active', true))))
            ->when($cat, fn ($w) => $w->where('category_id', $cat))
            ->when($brand, fn ($w) => $w->where('brand_id', $brand))
            ->when($q !== '', fn ($w) => self::search($w, $q))
            ->withCount('documents')->orderBy('brand')->orderBy('type')->paginate(60)->withQueryString();
        $with = ['suppliers', 'documents.user', 'productCategory', 'productSubcategory', 'options.attribute', 'tags', 'brandRef'];
        $sel = $request->query('item') ? SalesItem::with($with)->find($request->query('item')) : null;
        $sel ??= $items->first()?->load($with);

        return view('psales.items', [
            'items' => $items, 'sel' => $sel, 'cat' => $cat, 'q' => $q, 'brand' => $brand, 'web' => $web,
            'brands' => Brand::orderBy('name')->get(['id', 'name', 'kind', 'active']),
            'canEdit' => $request->user()->hasRight('sales.edit_items'), 'seeCost' => $request->user()->hasRight('finance.see_margins'),
            'suppliers' => $request->user()->hasRight('sales.edit_items') ? Company::where('is_supplier', true)->orderBy('name')->get(['id', 'name']) : collect(),
            'contacts' => $sel ? Contact::with('companies')->whereNotNull('email')->orderBy('first_name')->limit(300)->get() : collect(),
            'new' => $request->boolean('new'),
        ] + Classification::forForm(SalesItem::CATALOGUE));
    }

    /**
     * Every word must match one of: our number, brand, type, description, search
     * words, the manufacturer's code, the EAN or a supplier's article number.
     * Codes also match without spaces, dashes or dots ("SM58-LCE" = "sm58lce").
     */
    public static function search($w, string $q)
    {
        foreach (preg_split('/\s+/', $q) as $word) {
            $like = '%'.$word.'%';
            $bare = preg_replace('/[\s.\-\/]/', '', Str::lower($word));
            $w->where(fn ($x) => $x->where('number', 'like', $like)->orWhere('brand', 'like', $like)->orWhere('type', 'like', $like)
                ->orWhere('description', 'like', $like)->orWhere('search_words', 'like', $like)->orWhere('ean', 'like', $like)
                ->orWhere('manufacturer_code', 'like', $like)
                ->when($bare !== '', fn ($y) => $y->orWhereRaw("replace(replace(replace(lower(manufacturer_code), '-', ''), '.', ''), ' ', '') like ?", ['%'.$bare.'%']))
                ->orWhereHas('suppliers', fn ($s) => $s->where('sales_item_suppliers.supplier_ref', 'like', $like)));
        }

        return $w;
    }

    private function rules(): array
    {
        return [
            'brand' => ['nullable', 'string', 'max:80'], 'type' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:300'],
            'category_id' => ['nullable', 'integer'], 'subcategory_id' => ['nullable', 'integer'], 'attr' => ['nullable', 'array'], 'attr.*' => ['nullable'],
            'tags' => ['nullable', 'array'], 'tags.*' => ['integer'], 'search_words' => ['nullable', 'string', 'max:1000'],
            'unit' => ['required', Rule::in(SalesItem::UNITS)],
            'list_price' => ['required', 'numeric', 'min:0', 'max:1000000'], 'stock_qty' => ['nullable', 'integer', 'min:0', 'max:1000000'], 'min_stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'ean' => ['nullable', 'string', 'max:32'], 'on_website' => ['nullable', 'boolean'], 'manufacturer_code' => ['nullable', 'string', 'max:60'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'width_cm' => ['nullable', 'numeric', 'min:0', 'max:10000'], 'length_cm' => ['nullable', 'numeric', 'min:0', 'max:10000'], 'height_cm' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ];
    }

    private function data(array $d): array
    {
        return ['brand' => $d['brand'] ?? null, 'type' => $d['type'], 'description' => $d['description'] ?? null, 'unit' => $d['unit'],
            'list_price_cents' => (int) round($d['list_price'] * 100), 'stock_qty' => (int) ($d['stock_qty'] ?? 0), 'min_stock' => (int) ($d['min_stock'] ?? 0), 'ean' => $d['ean'] ?? null,
            'manufacturer_code' => ($m = trim((string) ($d['manufacturer_code'] ?? ''))) !== '' ? $m : null,
            'weight_kg' => isset($d['weight_kg']) ? round((float) $d['weight_kg'], 3) : null,
            'width_m' => isset($d['width_cm']) ? round($d['width_cm'] / 100, 4) : null,
            'length_m' => isset($d['length_cm']) ? round($d['length_cm'] / 100, 4) : null,
            'height_m' => isset($d['height_cm']) ? round($d['height_cm'] / 100, 4) : null]
            + (auth()->user()?->hasRight('equipment.website') ? ['on_website' => (bool) ($d['on_website'] ?? false)] : []);
    }

    /** Category, subcategory, attributes and tags from the sales lists (Settings › Product categories › Sales products). */
    private function checkClass(array $d): array
    {
        return Classification::check(isset($d['category_id']) ? (int) $d['category_id'] : null, isset($d['subcategory_id']) ? (int) $d['subcategory_id'] : null,
            $d['attr'] ?? [], $d['tags'] ?? [], catalogue: SalesItem::CATALOGUE);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate($this->rules());
        $class = $this->checkClass($d);
        $item = SalesItem::create($this->data($d) + ['number' => SalesItem::nextNumber()]);
        Classification::apply($item, $class, $d['search_words'] ?? '');
        AuditLog::record('psales.item_created', $item, ['number' => $item->number], $request->user());

        return redirect()->route('psales.items', ['item' => $item->id])->with('status', __('psales.items.created', ['number' => $item->number]));
    }

    public function update(Request $request, SalesItem $item): RedirectResponse
    {
        $old = $item->only(['list_price_cents', 'stock_qty']);
        $d = $request->validate($this->rules());
        $class = $this->checkClass($d);
        $item->update($this->data($d));
        Classification::apply($item, $class, $d['search_words'] ?? '');
        AuditLog::record('psales.item_updated', $item, ['number' => $item->number, 'price' => $old['list_price_cents'] !== $item->list_price_cents ? $old['list_price_cents'].' → '.$item->list_price_cents : null], $request->user());

        return back()->with('status', __('psales.items.saved'));
    }

    public function photo(Request $request, SalesItem $item): RedirectResponse
    {
        $request->validate(['photo' => array_merge(['required'], Images::rule())]);
        Images::delete($item->photo_path);
        $item->update(['photo_path' => Images::store($request->file('photo'), 'sales-items', 900)]);

        return back();
    }

    public function photoShow(SalesItem $item)
    {
        abort_unless($item->photo_path && Storage::disk('local')->exists($item->photo_path), 404);

        return response()->file(Storage::disk('local')->path($item->photo_path), ['Cache-Control' => 'private, max-age=604800']);
    }

    public function supplier(Request $request, SalesItem $item): RedirectResponse
    {
        $d = $request->validate(['company_id' => ['required', 'integer', 'exists:companies,id'], 'discount' => ['required', 'numeric', 'min:0', 'max:100'],
            'lead_days' => ['required', 'integer', 'min:0', 'max:365'], 'preferred' => ['boolean'], 'remove' => ['boolean'],
            'supplier_ref' => ['nullable', 'string', 'max:60']]);
        if ($request->boolean('remove')) {
            $item->suppliers()->detach($d['company_id']);
        } else {
            if ($request->boolean('preferred') || $item->suppliers()->count() === 0) {
                $item->suppliers()->newPivotQuery()->update(['preferred' => false]);
            }
            $ref = trim((string) ($d['supplier_ref'] ?? ''));
            $item->suppliers()->syncWithoutDetaching([$d['company_id'] => ['discount_bp' => (int) round($d['discount'] * 100), 'lead_days' => $d['lead_days'],
                'preferred' => $request->boolean('preferred') || $item->suppliers()->count() === 0] + ($ref !== '' ? ['supplier_ref' => $ref] : [])]);
            Company::whereKey($d['company_id'])->update(['is_supplier' => true]);
        }
        AuditLog::record('psales.item_supplier', $item, ['supplier' => Company::find($d['company_id'])?->name, 'discount' => $d['discount'].'%', 'ref' => $d['supplier_ref'] ?? null], $request->user());

        return back()->with('status', __('psales.items.saved'));
    }

    public function upload(Request $request, SalesItem $item): RedirectResponse
    {
        $request->validate(['kind' => ['required', Rule::in(ItemDocument::KINDS)], 'files' => ['required', 'array', 'max:10'], 'files.*' => ['file', 'mimes:pdf', 'max:20480']]);
        foreach ($request->file('files') as $f) {
            $item->documents()->create(['kind' => $request->input('kind'), 'name' => mb_substr($f->getClientOriginalName(), 0, 200),
                'path' => $f->store('sales-items/'.$item->id, 'local'), 'size' => $f->getSize(), 'user_id' => $request->user()->id]);
        }

        return back()->with('status', __('psales.items.uploaded'));
    }

    public function download(SalesItem $item, ItemDocument $doc)
    {
        abort_unless($doc->sales_item_id === $item->id && Storage::disk('local')->exists($doc->path), 404);

        return response()->file(Storage::disk('local')->path($doc->path), ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.addslashes($doc->name).'"']);
    }

    /** A PDF from a link in an email (signed, 30 days). */
    public function publicDoc(ItemDocument $doc)
    {
        abort_unless(Storage::disk('local')->exists($doc->path), 404);

        return response()->file(Storage::disk('local')->path($doc->path), ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.addslashes($doc->name).'"']);
    }

    public function removeDoc(SalesItem $item, ItemDocument $doc): RedirectResponse
    {
        abort_unless($doc->sales_item_id === $item->id, 404);
        Storage::disk('local')->delete($doc->path);
        $doc->delete();

        return back();
    }

    /** Send the ticked PDFs to a contact, in the contact's language; logged on the company. */
    public function send(Request $request, SalesItem $item): RedirectResponse
    {
        $d = $request->validate(['docs' => ['required', 'array'], 'docs.*' => ['integer'], 'contact_id' => ['required', 'integer', 'exists:contacts,id'], 'message' => ['nullable', 'string', 'max:2000']]);
        $contact = Contact::with('companies')->findOrFail($d['contact_id']);
        $docs = $item->documents()->whereIn('id', $d['docs'])->get();
        abort_if($docs->isEmpty() || ! $contact->email, 422);
        $loc = array_key_exists((string) $contact->language, config('cluk.locales')) ? $contact->language : 'en';
        // Microsoft 365 takes attachments up to about 3 MB per mail; bigger files go as download links (30 days).
        $attach = $docs->sum('size') <= 3 * 1024 * 1024;
        $links = $attach ? [] : $docs->map(fn ($x) => URL::temporarySignedRoute('psales.doc.public', now()->addDays(30), ['doc' => $x->id]))->all();
        $mail = InLocale::run($loc, fn () => new Confirmation(
            subject: __('psales.items.mail_subject', ['item' => $item->name()]),
            greeting: __('mails.hello', ['name' => $contact->first_name]),
            intro: ($d['message'] ?? null) ? preg_split('/\R{2,}/', trim($d['message'])) : [__('psales.items.mail_intro', ['item' => $item->name()])],
            details: $docs->values()->mapWithKeys(fn ($x, $i) => [__('psales.doc_kinds.'.$x->kind).' · '.($i + 1) => $x->name])->all(),
            next: $links ? array_merge([__('psales.items.mail_links')], $links) : [],
            signer: $request->user(),
        ))->locale($loc);
        if ($attach) {
            $mail->attachFiles = $docs->map(fn ($x) => ['path' => Storage::disk('local')->path($x->path), 'name' => $x->name])->all();
        }
        $ok = SafeMail::notify(Notification::route('mail', $contact->email), $mail);
        foreach ($contact->companies as $c) {
            AuditLog::record('psales.docs_sent', $c, ['item' => $item->number, 'to' => $contact->email, 'files' => $docs->pluck('name')->implode(', ')], $request->user());
        }

        return back()->with('status', $ok ? __('psales.items.sent', ['n' => $docs->count(), 'to' => $contact->name]) : __('psales.items.not_sent'));
    }
}
