<?php

namespace App\Services\Products;

use App\Models\Equipment\Item;
use App\Models\Product\Attribute;
use App\Models\Product\AttributeOption;
use App\Models\Product\Category;
use App\Models\Product\Subcategory;
use App\Models\Product\Tag;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The rules of the catalogue lists, the same for the item form and the import:
 * the subcategory belongs to the category, attributes only for the
 * subcategories they apply to, one choice unless the attribute allows several,
 * only known tags. Re-classifying never touches the item number.
 */
class Classification
{
    /**
     * @param  array<string, string|list<string>|null>  $values  attribute key => option id(s) (form) or value(s) (import)
     * @param  list<int|string>  $tags  tag ids or slugs
     * @return array{sub: ?Subcategory, options: list<int>, tags: list<int>}
     */
    public static function check(?int $categoryId, ?int $subcategoryId, array $values = [], array $tags = [], bool $byValue = false, string $field = ''): array
    {
        $errors = [];
        $sub = $subcategoryId ? Subcategory::with(['category', 'attrs.options'])->find($subcategoryId) : null;
        if ($subcategoryId && ! $sub) {
            $errors[$field.'subcategory_id'] = __('products.errors.unknown_subcategory');
        } elseif ($sub && $categoryId && $sub->category_id !== $categoryId) {
            $errors[$field.'subcategory_id'] = __('products.errors.wrong_category', ['sub' => $sub->label(), 'cat' => Category::find($categoryId)?->label() ?? '?']);
        } elseif (! $sub && $categoryId) {
            $errors[$field.'subcategory_id'] = __('products.errors.subcategory_required');
        }

        $options = [];
        $allowed = $sub ? $sub->attrs->keyBy('key') : collect();
        foreach ($values as $key => $chosen) {
            $chosen = array_values(array_filter((array) $chosen, fn ($v) => $v !== null && $v !== ''));
            if (! $chosen) {
                continue;
            }
            /** @var Attribute|null $attr */
            $attr = $allowed->get($key);
            if (! $attr) {
                $name = Attribute::where('key', $key)->first()?->label() ?? $key;
                $errors[$field.'attr.'.$key] = __('products.errors.attribute_not_here', ['attr' => $name, 'sub' => $sub?->label() ?? '—']);

                continue;
            }
            if (! $attr->multiple && count($chosen) > 1) {
                $errors[$field.'attr.'.$key] = __('products.errors.one_choice', ['attr' => $attr->label()]);

                continue;
            }
            foreach ($chosen as $c) {
                $opt = $byValue
                    ? $attr->options->first(fn (AttributeOption $o) => self::same($o->value, (string) $c) || collect($o->label ?? [])->contains(fn ($l) => self::same((string) $l, (string) $c)))
                    : $attr->options->firstWhere('id', (int) $c);
                if (! $opt) {
                    $errors[$field.'attr.'.$key] = __('products.errors.unknown_choice', ['value' => $c, 'attr' => $attr->label()]);

                    continue 2;
                }
                $options[] = $opt->id;
            }
        }

        $tagIds = [];
        if ($tags) {
            $known = Tag::all();
            foreach ($tags as $t) {
                $tag = is_numeric($t) ? $known->firstWhere('id', (int) $t) : $known->first(fn (Tag $k) => self::same($k->slug, (string) $t) || collect($k->name)->contains(fn ($n) => self::same((string) $n, (string) $t)));
                if (! $tag) {
                    $errors[$field.'tags'] = __('products.errors.unknown_tag', ['tag' => $t]);

                    continue;
                }
                $tagIds[] = $tag->id;
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return ['sub' => $sub, 'options' => array_values(array_unique($options)), 'tags' => array_values(array_unique($tagIds))];
    }

    /** Save a checked classification on an item (after save, so it has an id). */
    public static function apply(Item $item, array $checked, ?string $searchWords = null, bool $withTags = true): void
    {
        $item->placeIn($checked['sub']);
        if ($searchWords !== null) {
            $item->search_words = trim($searchWords) !== '' ? mb_substr(trim($searchWords), 0, 1000) : null;
        }
        $item->save();
        $item->options()->sync($checked['options']);
        if ($withTags) {
            $item->tags()->sync($checked['tags']);
        }
    }

    /** Find a category by code or name in any language (import). */
    public static function findCategory(string $text): ?Category
    {
        return Category::all()->first(fn (Category $c) => self::same($c->code, $text) || collect($c->name)->contains(fn ($n) => self::same((string) $n, $text)));
    }

    public static function findSubcategory(Category $cat, string $text): ?Subcategory
    {
        return $cat->subcategories->first(fn (Subcategory $s) => self::same($s->code, $text) || collect($s->name)->contains(fn ($n) => self::same((string) $n, $text)));
    }

    /** Values typed in a sheet: "2,5 m" = "2.5", "Zwart" = "black", case and spaces don't matter. */
    public static function same(string $a, string $b): bool
    {
        $n = fn (string $s) => preg_replace('/\s+/', ' ', trim(str_replace([',', ' m', ' MHz', '-'], ['.', '', '', '–'], mb_strtolower($s))));

        return $n($a) === $n($b);
    }

    /** All lists for a form: categories with their subcategories, attributes with options and the subcategories they apply to. */
    public static function forForm(): array
    {
        return [
            'categories' => Category::with(['subcategories.attrs'])->orderBy('sort')->get(),
            'attrList' => Attribute::with(['options' => fn ($q) => $q->where('active', true), 'subcategories:id'])->orderBy('sort')->get(),
            'tags' => Tag::where('active', true)->orderBy('sort')->get(),
        ];
    }

    /** @return Collection<int, Subcategory> */
    public static function activeSubcategories(): Collection
    {
        return Subcategory::with('category')->where('active', true)->orderBy('sort')->get();
    }
}
