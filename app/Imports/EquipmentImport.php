<?php

namespace App\Imports;

use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\Product\AttributeOption;
use App\Models\Product\Subcategory;
use App\Services\Products\Classification;
use Illuminate\Validation\ValidationException;

/**
 * Rental equipment from the CLUK template (sheet "Equipment") or an export.
 * One row per piece: rows with the same item number belong to one item;
 * a row with a serial number becomes an asset (own QR label); a row with a
 * quantity and no serial number is a counted item (cables, safeties).
 */
class EquipmentImport extends Importer
{
    /** @var array<string, bool> item numbers seen in this file */
    private array $seen = [];

    public static function sheetNames(): array
    {
        return ['equipment', 'materiaal', 'materieel', 'verhuurmateriaal', 'rentalequipment', 'stock', 'items', 'equipo', 'materiel'];
    }

    protected function requiredField(): string
    {
        return 'number';
    }

    protected function columns(): array
    {
        return [
            'number' => ['Itemnumber', 'Item number', 'Item no', 'Artikelnummer', 'Artikel nr', 'Part number', 'SKU', 'Code', 'Référence', 'Referencia'],
            'category' => ['Category', 'Categorie', 'Catégorie', 'Categoría', 'Group', 'Groep'],
            'subcategory' => ['Sub-Category', 'Subcategory', 'Subcategorie', 'Sous-catégorie', 'Subcategoría'],
            'unit' => ['Unit', 'Eenheid', 'Unité', 'Unidad'],
            'brand' => ['Brand', 'Merk', 'Marque', 'Marca', 'Manufacturer', 'Fabrikant'],
            'type' => ['Type', 'Model', 'Modèle', 'Modelo'],
            'name' => ['Name', 'Item name', 'Naam', 'Nom', 'Nombre'],
            'description' => ['Description', 'Omschrijving', 'Beschrijving', 'Descripción'],
            'day_price' => ['Rental price per day', 'Day price', 'Price per day', 'Hire price', 'Daily price', 'Dagprijs', 'Huurprijs per dag', 'Prix par jour', 'Precio por día'],
            'catalog' => ['Catalog Price', 'Catalogue price', 'Replacement value', 'Cataloguswaarde', 'Catalogusprijs', 'Prix catalogue', 'Precio de catálogo'],
            'origin' => ['Country of Origin', 'Origin', 'Land van oorsprong', 'Pays d\'origine', 'País de origen'],
            'hs_code' => ['HS-Code', 'HS code', 'Commodity code', 'Douanecode'],
            'weight' => ['Weight (kg)', 'Weight', 'Gewicht', 'Poids', 'Peso'],
            'width' => ['Width (m)', 'Width', 'Breedte', 'Largeur', 'Ancho'],
            'length' => ['Length (m)', 'Length', 'Depth (m)', 'Lengte', 'Diepte', 'Longueur', 'Largo'],
            'height' => ['Height (m)', 'Height', 'Hoogte', 'Hauteur', 'Alto'],
            'serial' => ['Serial number', 'Serial', 'Serial no', 'Serienummer', 'Numéro de série', 'Número de serie'],
            'quantity' => ['Quantity', 'Qty', 'Stock', 'Aantal', 'Quantité', 'Cantidad'],
            // The fixed catalogue lists (Settings › Product categories). "Length (m)" above is the size for transport.
            'attr_length' => ['Catalogue length (m)', 'Cable length', 'Truss length', 'Lengte catalogus', 'Longueur catalogue', 'Longitud catálogo'],
            'attr_frequency_band' => ['Frequency band', 'Frequency bands', 'Frequentieband', 'Bande de fréquence', 'Banda de frecuencia'],
            'attr_frequency_range' => ['Frequency range', 'Frequency range (MHz)', 'Frequentiebereik', 'Plage de fréquences', 'Rango de frecuencias'],
            'attr_connector' => ['Connector', 'Connecteur', 'Conector'],
            'attr_colour' => ['Colour', 'Color', 'Kleur', 'Couleur'],
            'attr_truss_series' => ['Truss series', 'Trussreeks', 'Série de structure', 'Serie de truss'],
            'tags' => ['Tags', 'Labels', 'Étiquettes', 'Etiquetas'],
            'search_words' => ['Search words', 'Zoekwoorden', 'Mots de recherche', 'Palabras de búsqueda'],
        ];
    }

    protected function importRow(array $row, int $line): void
    {
        // Texts longer than their field are cut to fit, never a crash halfway through a file.
        $fit = fn (?string $v, int $len) => $v === null ? null : mb_substr($v, 0, $len);
        $number = strtoupper(trim((string) $row['number']));
        if (mb_strlen($number) > 40) {
            $this->report['skipped']++;
            $this->message($line, 'error', __('import.errors.number_long', ['number' => $number]));

            return;
        }
        if ($number === '') {
            $this->report['skipped']++;
            $this->message($line, 'error', __('import.errors.no_number'));

            return;
        }

        $serial = trim((string) ($row['serial'] ?? ''));
        $qty = self::number($row['quantity'] ?? null);
        $item = Item::firstOrNew(['number' => $number]);
        $isNew = ! $item->exists;
        $first = ! isset($this->seen[$number]);
        $this->seen[$number] = true;

        // Item details: from the first row of this item in the file; empty cells never wipe.
        if ($first) {
            $brand = $fit(self::text($row['brand'] ?? null), 80);
            $type = $fit(self::text($row['type'] ?? null), 255);
            // Category, subcategory, attributes and tags must be in the fixed lists; otherwise the row is refused.
            try {
                $class = $this->classification($row, $item);
            } catch (ValidationException $e) {
                $this->report['skipped']++;
                $this->message($line, 'error', implode(' ', $e->validator->errors()->all()));
                unset($this->seen[$number]);

                return;
            }
            $values = array_filter([
                'unit' => $fit(self::text($row['unit'] ?? null), 12),
                'brand' => $brand,
                'type' => $type,
                'origin' => ($o = self::country($row['origin'] ?? null)) && strlen($o) === 2 ? $o : null,
                'hs_code' => self::text($row['hs_code'] ?? null),
                'weight_kg' => self::number($row['weight'] ?? null),
                'width_m' => self::number($row['width'] ?? null),
                'length_m' => self::number($row['length'] ?? null),
                'height_m' => self::number($row['height'] ?? null),
            ], fn ($v) => $v !== null);
            foreach (['day_price' => 'day_price_cents', 'catalog' => 'catalog_price_cents'] as $col => $field) {
                if (($p = self::number($row[$col] ?? null)) !== null) {
                    $values[$field] = (int) round($p * 100);
                } elseif (! empty($row[$col])) {
                    $this->message($line, 'warning', __('import.warnings.price', ['value' => $row[$col]]));
                }
            }
            $item->fill($values);
            $name = self::text($row['name'] ?? null) ?? trim(($brand ?? '').' '.($type ?? ''));
            if ($name !== '' || $isNew) {
                $item->name = mb_substr($name !== '' ? $name : (self::text($row['description'] ?? null) ?? $number), 0, 255);
            }
            if ($d = self::text($row['description'] ?? null)) {
                $item->descriptions = array_merge($item->descriptions ?? [], ['en' => $d]);
            }
            if ($isNew) {
                $item->serial = $serial !== '';
            }
            if ($class) {
                $item->placeIn($class['sub']);
                if (($w = self::text($row['search_words'] ?? null)) !== null) {
                    $item->search_words = mb_substr($w, 0, 1000);
                }
            }
            $pendingClass = $class;
        }

        if ($serial !== '') {
            $item->serial = true;
            $changed = $item->isDirty();
            $item->save();
            $this->saveClass($item, $pendingClass ?? null, $row);
            if ($item->assets()->where('serial_number', $serial)->exists()) {
                $this->report[$changed ? 'updated' : 'skipped']++;

                return;
            }
            $item->assets()->create(['code' => Asset::nextCode($item), 'serial_number' => $serial, 'status' => Asset::AVAILABLE]);
            $this->report[$isNew ? 'created' : 'updated']++;

            return;
        }

        if ($qty !== null) {
            if ($item->exists && $item->serial && $item->assets()->exists()) {
                $this->message($line, 'warning', __('import.warnings.qty_serial', ['number' => $number]));
            } else {
                $item->serial = false;
                $item->stock_qty = (int) $qty;
            }
        }
        $changed = $item->isDirty();
        $item->save();
        $changed = $this->saveClass($item, $pendingClass ?? null, $row) || $changed;
        $this->report[$isNew ? 'created' : ($changed ? 'updated' : 'skipped')]++;
    }

    /**
     * The row's place in the catalogue, checked against the lists: null when the
     * row says nothing about it (an existing item keeps its place).
     *
     * @return array{sub: mixed, options: list<int>, tags: list<int>}|null
     */
    private function classification(array $row, Item $item): ?array
    {
        $cat = self::text($row['category'] ?? null);
        $sub = self::text($row['subcategory'] ?? null);
        $attrs = [];
        foreach ($row as $k => $v) {
            if (str_starts_with((string) $k, 'attr_') && self::text($v) !== null) {
                $attrs[substr($k, 5)] = array_values(array_filter(array_map('trim', preg_split('/[,;]/', (string) $v))));
            }
        }
        $tags = array_values(array_filter(array_map('trim', preg_split('/[,;]/', (string) ($row['tags'] ?? '')))));
        if ($cat === null && $sub === null && ! $attrs && ! $tags) {
            if (! $item->exists && ($item->kind ?? 'item') !== 'service') {
                throw ValidationException::withMessages(['subcategory_id' => __('import.errors.no_category')]);
            }

            return null;
        }
        if ($cat !== null || $sub !== null) {
            $category = $cat !== null ? Classification::findCategory($cat) : null;
            if ($cat !== null && ! $category) {
                throw ValidationException::withMessages(['category_id' => __('import.errors.unknown_category', ['value' => $cat])]);
            }
            if ($sub === null) {
                throw ValidationException::withMessages(['subcategory_id' => __('products.errors.subcategory_required')]);
            }
            $found = $category ? Classification::findSubcategory($category, $sub) : null;
            if (! $found) {
                $elsewhere = Subcategory::all()->first(fn ($s) => Classification::same($s->code, $sub) || collect($s->name)->contains(fn ($n) => Classification::same((string) $n, $sub)));
                throw ValidationException::withMessages(['subcategory_id' => $elsewhere && $category
                    ? __('products.errors.wrong_category', ['sub' => $elsewhere->label(), 'cat' => $category->label()])
                    : __('import.errors.unknown_subcategory', ['value' => $sub])]);
            }
            $catId = $found->category_id;
            $subId = $found->id;
        } else {
            $catId = $item->category_id;
            $subId = $item->subcategory_id;
        }

        return Classification::check($catId, $subId, $attrs, $tags, byValue: true);
    }

    /** Attribute values and tags from the sheet; returns whether something changed. */
    private function saveClass(Item $item, ?array $class, array $row): bool
    {
        if (! $class) {
            return false;
        }
        // Values in the sheet replace that attribute's values; other values stay if they still fit the subcategory (empty cells never wipe).
        $given = AttributeOption::whereIn('id', $class['options'])->pluck('attribute_id')->unique();
        $valid = $class['sub']?->attrs->pluck('id') ?? collect();
        $keep = $item->options()->get()->filter(fn ($o) => $valid->contains($o->attribute_id) && ! $given->contains($o->attribute_id))->pluck('id')->all();
        $a = $item->options()->sync(array_merge($keep, $class['options']));
        $t = $class['tags'] ? $item->tags()->syncWithoutDetaching($class['tags']) : ['attached' => []];

        return (bool) array_filter([$a['attached'], $a['detached'], $t['attached']]);
    }

    private static function text(?string $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }

    /** "9850", "9.850,00", "13,5", "€ 65" → float; formulas or text → null. */
    private static function number(?string $v): ?float
    {
        $v = trim(str_replace(['€', ' ', "\u{00A0}"], '', (string) $v));
        if ($v === '' || str_starts_with($v, '=')) {
            return null;
        }
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $v)) {
            $v = str_replace(['.', ','], ['', '.'], $v);
        } elseif (preg_match('/^\d+,\d+$/', $v)) {
            $v = str_replace(',', '.', $v);
        } else {
            $v = str_replace(',', '', $v);
        }

        return is_numeric($v) ? (float) $v : null;
    }
}
