<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Equipment\Item;
use App\Models\Product\Attribute;
use App\Models\Product\AttributeOption;
use App\Models\Product\Category;
use App\Models\Product\Subcategory;
use App\Models\Product\Tag;
use App\Services\Media\Translator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Settings › Product categories: the fixed lists behind the catalogue and the
 * website. Safe by design: the website links to slugs, a rename keeps the
 * slug, a changed slug keeps the old one as a redirect, a subcategory never
 * moves to another category, and nothing in use can be deleted (only switched
 * off, after its items are moved). Item numbers are never touched.
 */
class ProductListController extends Controller
{
    public function index(): View
    {
        return view('settings.product-lists', [
            'categories' => Category::with('subcategories')->orderBy('sort')->get(),
            'attrList' => Attribute::with(['options', 'subcategories:id'])->orderBy('sort')->get(),
            'tags' => Tag::orderBy('sort')->get(),
            'subCounts' => Item::whereNotNull('subcategory_id')->selectRaw('subcategory_id, count(*) n')->groupBy('subcategory_id')->pluck('n', 'subcategory_id'),
            'catCounts' => Item::whereNotNull('category_id')->selectRaw('category_id, count(*) n')->groupBy('category_id')->pluck('n', 'category_id'),
            'optCounts' => DB::table('equipment_item_options')->selectRaw('option_id, count(*) n')->groupBy('option_id')->pluck('n', 'option_id'),
            'tagCounts' => DB::table('equipment_item_tag')->selectRaw('tag_id, count(*) n')->groupBy('tag_id')->pluck('n', 'tag_id'),
            'allSubs' => Subcategory::with('category')->orderBy('category_id')->orderBy('sort')->get(),
            'canTranslate' => Translator::available(),
        ]);
    }

    // ── Categories ───────────────────────────────────────────────────────────

    public function storeCategory(Request $request): RedirectResponse
    {
        $d = $this->validateNamed($request, ['code' => ['required', 'string', 'max:6', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('product_categories', 'code')]]);
        $c = Category::create(['code' => strtoupper($d['code']), 'slug' => $this->freeSlug(Category::query(), $d['name']['en']), 'name' => $d['name'],
            'sort' => (int) Category::max('sort') + 10, 'active' => false]);
        AuditLog::record('products.category_added', $c, ['name' => $c->label('en')], $request->user());

        return back()->with('status', __('products.flash.added_hidden'));
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        $d = $this->validateNamed($request, [
            'code' => ['required', 'string', 'max:6', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('product_categories', 'code')->ignore($category->id)],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('product_categories', 'slug')->ignore($category->id)],
            'sort' => ['nullable', 'integer'], 'active' => ['nullable', 'boolean'],
        ]);
        $this->fillSafe($category, $d);
        $this->audit($request, 'products.category_saved', $category);

        return back()->with('status', __('products.flash.saved'));
    }

    public function destroyCategory(Request $request, Category $category): RedirectResponse
    {
        if ($category->subcategories()->exists() || Item::where('category_id', $category->id)->exists()) {
            throw ValidationException::withMessages(['category' => __('products.errors.in_use')]);
        }
        AuditLog::record('products.category_deleted', null, ['name' => $category->label('en')], $request->user());
        $category->delete();

        return back()->with('status', __('products.flash.deleted'));
    }

    // ── Subcategories ────────────────────────────────────────────────────────

    public function storeSubcategory(Request $request, Category $category): RedirectResponse
    {
        $d = $this->validateNamed($request, ['code' => ['required', 'string', 'max:6', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('product_subcategories', 'code')->where('category_id', $category->id)]]);
        $s = $category->subcategories()->create(['code' => strtoupper($d['code']), 'slug' => $this->freeSlug(Subcategory::where('category_id', $category->id), $d['name']['en']),
            'name' => $d['name'], 'sort' => (int) $category->subcategories()->max('sort') + 10, 'active' => false]);
        AuditLog::record('products.subcategory_added', $s, ['name' => $s->label('en'), 'category' => $category->label('en')], $request->user());

        return back()->with('status', __('products.flash.added_hidden'));
    }

    public function updateSubcategory(Request $request, Subcategory $subcategory): RedirectResponse
    {
        $d = $this->validateNamed($request, [
            'code' => ['required', 'string', 'max:6', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('product_subcategories', 'code')->where('category_id', $subcategory->category_id)->ignore($subcategory->id)],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('product_subcategories', 'slug')->where('category_id', $subcategory->category_id)->ignore($subcategory->id)],
            'sort' => ['nullable', 'integer'], 'active' => ['nullable', 'boolean'],
            'move_to' => ['nullable', 'integer', Rule::exists('product_subcategories', 'id')->where('active', true)],
        ]);
        $inUse = Item::where('subcategory_id', $subcategory->id)->count();
        if (empty($d['active']) && $subcategory->active && $inUse) {
            // Switching off a subcategory that items use: they move first, keeping their numbers.
            $to = isset($d['move_to']) ? Subcategory::find($d['move_to']) : null;
            if (! $to || $to->is($subcategory)) {
                throw ValidationException::withMessages(['move_to' => __('products.errors.move_first', ['n' => $inUse])]);
            }
            foreach (Item::where('subcategory_id', $subcategory->id)->get() as $item) {
                $item->placeIn($to);
                $item->save();
                $item->options()->detach($item->options->filter(fn ($o) => ! $to->attrs->contains('id', $o->attribute_id))->pluck('id'));
            }
            AuditLog::record('products.items_moved', $subcategory, ['to' => $to->label('en'), 'items' => $inUse], $request->user());
        }
        $this->fillSafe($subcategory, $d);
        // The English name is also kept in the items' text column (read by the warehouse).
        Item::where('subcategory_id', $subcategory->id)->update(['subcategory' => $subcategory->label('en')]);
        $this->audit($request, 'products.subcategory_saved', $subcategory);

        return back()->with('status', __('products.flash.saved'));
    }

    public function destroySubcategory(Request $request, Subcategory $subcategory): RedirectResponse
    {
        if (Item::where('subcategory_id', $subcategory->id)->exists()) {
            throw ValidationException::withMessages(['subcategory' => __('products.errors.in_use')]);
        }
        AuditLog::record('products.subcategory_deleted', null, ['name' => $subcategory->label('en')], $request->user());
        $subcategory->delete();

        return back()->with('status', __('products.flash.deleted'));
    }

    // ── Attributes and their choices ─────────────────────────────────────────

    public function storeAttribute(Request $request): RedirectResponse
    {
        $d = $this->validateNamed($request, ['multiple' => ['nullable', 'boolean'], 'unit' => ['nullable', 'string', 'max:10']]);
        $key = Str::slug($d['name']['en'], '_');
        if (Attribute::where('key', $key)->exists()) {
            throw ValidationException::withMessages(['name.en' => __('products.errors.exists')]);
        }
        $a = Attribute::create(['key' => $key, 'name' => $d['name'], 'multiple' => (bool) ($d['multiple'] ?? false), 'unit' => $d['unit'] ?? null, 'sort' => (int) Attribute::max('sort') + 10]);
        AuditLog::record('products.attribute_added', $a, ['name' => $a->label('en')], $request->user());

        return back()->with('status', __('products.flash.saved'));
    }

    public function updateAttribute(Request $request, Attribute $attribute): RedirectResponse
    {
        $d = $this->validateNamed($request, ['unit' => ['nullable', 'string', 'max:10'], 'subs' => ['nullable', 'array'], 'subs.*' => ['integer', 'exists:product_subcategories,id']]);
        $before = $attribute->subcategories()->pluck('product_subcategories.id')->all();
        $after = array_map('intval', $d['subs'] ?? []);
        $removed = array_diff($before, $after);
        if ($removed && DB::table('equipment_item_options')->join('equipment_items', 'equipment_items.id', '=', 'equipment_item_options.item_id')
            ->whereIn('equipment_items.subcategory_id', $removed)->whereIn('option_id', $attribute->options()->pluck('id'))->exists()) {
            throw ValidationException::withMessages(['subs' => __('products.errors.attribute_in_use')]);
        }
        $attribute->fill(['name' => $d['name'], 'unit' => $d['unit'] ?? null])->save();
        $attribute->subcategories()->sync($after);
        AuditLog::record('products.attribute_saved', $attribute, ['name' => $attribute->label('en'), 'subcategories' => count($after)], $request->user());

        return back()->with('status', __('products.flash.saved'));
    }

    public function storeOption(Request $request, Attribute $attribute): RedirectResponse
    {
        $d = $request->validate(['value' => ['required', 'string', 'max:60', Rule::unique('product_attribute_options', 'value')->where('attribute_id', $attribute->id)],
            'label' => ['nullable', 'array'], 'label.*' => ['nullable', 'string', 'max:60']]);
        $label = array_filter($d['label'] ?? [], 'filled');
        $o = $attribute->options()->create(['value' => $attribute->unit ? str_replace(',', '.', $d['value']) : $d['value'], 'label' => $label ?: null,
            'sort' => (int) $attribute->options()->max('sort') + 10, 'active' => true]);
        AuditLog::record('products.option_added', $o, ['attribute' => $attribute->label('en'), 'value' => $o->value], $request->user());

        return back()->with('status', __('products.flash.saved'));
    }

    public function updateOption(Request $request, AttributeOption $option): RedirectResponse
    {
        $d = $request->validate(['label' => ['nullable', 'array'], 'label.*' => ['nullable', 'string', 'max:60'], 'active' => ['nullable', 'boolean'], 'sort' => ['nullable', 'integer']]);
        $option->fill(['label' => array_filter($d['label'] ?? [], 'filled') ?: null, 'active' => (bool) ($d['active'] ?? false), 'sort' => $d['sort'] ?? $option->sort])->save();
        AuditLog::record('products.option_saved', $option, ['value' => $option->value, 'active' => $option->active], $request->user());

        return back()->with('status', __('products.flash.saved'));
    }

    public function destroyOption(Request $request, AttributeOption $option): RedirectResponse
    {
        if (DB::table('equipment_item_options')->where('option_id', $option->id)->exists()) {
            throw ValidationException::withMessages(['option' => __('products.errors.in_use')]);
        }
        AuditLog::record('products.option_deleted', null, ['value' => $option->value], $request->user());
        $option->delete();

        return back()->with('status', __('products.flash.deleted'));
    }

    // ── Tags ─────────────────────────────────────────────────────────────────

    public function storeTag(Request $request): RedirectResponse
    {
        $d = $this->validateNamed($request);
        $t = Tag::create(['slug' => $this->freeSlug(Tag::query(), $d['name']['en']), 'name' => $d['name'], 'sort' => (int) Tag::max('sort') + 10, 'active' => true]);
        AuditLog::record('products.tag_added', $t, ['name' => $t->label('en')], $request->user());

        return back()->with('status', __('products.flash.saved'));
    }

    public function updateTag(Request $request, Tag $tag): RedirectResponse
    {
        $d = $this->validateNamed($request, ['sort' => ['nullable', 'integer'], 'active' => ['nullable', 'boolean']]);
        $tag->fill(['name' => $d['name'], 'sort' => $d['sort'] ?? $tag->sort, 'active' => (bool) ($d['active'] ?? false)])->save();
        AuditLog::record('products.tag_saved', $tag, ['name' => $tag->label('en')], $request->user());

        return back()->with('status', __('products.flash.saved'));
    }

    public function destroyTag(Request $request, Tag $tag): RedirectResponse
    {
        if (DB::table('equipment_item_tag')->where('tag_id', $tag->id)->exists()) {
            throw ValidationException::withMessages(['tag' => __('products.errors.in_use')]);
        }
        AuditLog::record('products.tag_deleted', null, ['name' => $tag->label('en')], $request->user());
        $tag->delete();

        return back()->with('status', __('products.flash.deleted'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Names in four languages; English required, empty ones filled by Claude when asked. */
    private function validateNamed(Request $request, array $more = []): array
    {
        $d = $request->validate($more + ['name' => ['required', 'array'], 'name.en' => ['required', 'string', 'max:80'], 'name.*' => ['nullable', 'string', 'max:80'], 'translate' => ['nullable', 'boolean']]);
        $name = array_filter(array_intersect_key($d['name'], config('cluk.locales')), 'filled');
        if (! empty($d['translate']) && count($name) < count(config('cluk.locales')) && Translator::available()) {
            foreach ((new Translator)->translate(['name' => $name['en']]) ?? [] as $loc => $f) {
                $name[$loc] ??= $f['name'] ?? null;
            }
        }
        $d['name'] = array_filter($name);

        return $d;
    }

    /** Rename freely; a new slug keeps the old one as a redirect for the website. */
    private function fillSafe(Category|Subcategory $m, array $d): void
    {
        $m->fill(['code' => strtoupper($d['code']), 'name' => $d['name'], 'sort' => $d['sort'] ?? $m->sort, 'active' => (bool) ($d['active'] ?? false)]);
        if (! empty($d['slug']) && $d['slug'] !== $m->slug) {
            $m->old_slugs = array_values(array_unique(array_merge($m->old_slugs ?? [], [$m->slug])));
            $m->slug = $d['slug'];
        }
        $m->save();
        if ($m instanceof Category) {
            Item::where('category_id', $m->id)->update(['category' => $m->label('en')]);
        }
    }

    private function freeSlug($query, string $name): string
    {
        $base = Str::slug(str_replace('&', 'and', $name)) ?: 'list';
        $slug = $base;
        for ($i = 2; (clone $query)->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    private function audit(Request $request, string $action, Category|Subcategory $m): void
    {
        AuditLog::record($action, $m, ['name' => $m->label('en'), 'code' => $m->code, 'slug' => $m->slug, 'active' => $m->active], $request->user());
    }
}
