<?php

namespace App\Imports;

use App\Models\Company;
use App\Models\ProductSales\SalesItem;
use App\Services\Products\Classification;
use App\Support\VatNumber;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sales products (Product sales › Items) from a spreadsheet. An existing item is
 * found by its S-number, else by brand + type; a supplier column links the
 * supplier (found by VAT number or name) with its discount and delivery time.
 */
class SalesItemImport extends Importer
{
    public static function sheetNames(): array
    {
        return ['salesitems', 'salesproducts', 'products', 'verkoopartikels', 'verkoopproducten', 'producten', 'produits', 'productos', 'articulos'];
    }

    protected function requiredField(): string
    {
        return 'type';
    }

    protected function columns(): array
    {
        return [
            'number' => ['Number', 'Item number', 'S-number', 'Nummer', 'Artikelnummer', 'Numéro', 'Número'],
            'brand' => ['Brand', 'Merk', 'Marque', 'Marca', 'Manufacturer', 'Fabrikant'],
            'type' => ['Type', 'Model', 'Product', 'Product name', 'Name', 'Naam', 'Modèle', 'Modelo'],
            'description' => ['Description', 'Omschrijving', 'Beschrijving', 'Désignation', 'Descripción'],
            'category' => ['Category', 'Categorie', 'Catégorie', 'Categoría'],
            'subcategory' => ['Sub-Category', 'Subcategory', 'Subcategorie', 'Sous-catégorie', 'Subcategoría'],
            'tags' => ['Tags', 'Labels', 'Étiquettes', 'Etiquetas'],
            'search_words' => ['Search words', 'Zoekwoorden', 'Mots de recherche', 'Palabras de búsqueda'],
            'unit' => ['Unit', 'Eenheid', 'Unité', 'Unidad'],
            'list_price' => ['List price', 'Price', 'Sales price', 'Selling price', 'Verkoopprijs', 'Lijstprijs', 'Prijs', 'Prix', 'Precio'],
            'stock' => ['Stock', 'In stock', 'Quantity', 'Qty', 'Voorraad', 'Aantal', 'Quantité', 'Cantidad'],
            'min_stock' => ['Minimum stock', 'Min stock', 'Minimum', 'Minimumvoorraad', 'Minimumstock', 'Stock minimum', 'Stock mínimo'],
            'ean' => ['EAN / barcode', 'EAN', 'Barcode', 'Streepjescode', 'Code-barres', 'Código de barras'],
            'preferred' => ['Preferred supplier', 'Voorkeursleverancier', 'Fournisseur préféré', 'Proveedor preferido'],
            'supplier_vat' => ['Supplier VAT number', 'Supplier VAT', 'Btw leverancier', 'TVA fournisseur'],
            's1' => ['Supplier 1', 'Supplier', 'Leverancier', 'Fournisseur', 'Proveedor'],
            's1_ref' => ['Supplier 1 article no.', 'Supplier article no.', 'Article number supplier'],
            's1_discount' => ['Supplier 1 discount %', 'Supplier discount', 'Discount', 'Discount %', 'Korting', 'Remise', 'Descuento'],
            's1_lead' => ['Supplier 1 lead time (working days)', 'Lead time', 'Lead days', 'Delivery days', 'Levertijd', 'Délai', 'Plazo'],
            's2' => ['Supplier 2'],
            's2_ref' => ['Supplier 2 article no.'],
            's2_discount' => ['Supplier 2 discount %'],
            's2_lead' => ['Supplier 2 lead time (working days)'],
            's3' => ['Supplier 3'],
            's3_ref' => ['Supplier 3 article no.'],
            's3_discount' => ['Supplier 3 discount %'],
            's3_lead' => ['Supplier 3 lead time (working days)'],
        ];
    }

    protected function importRow(array $row, int $line): void
    {
        $type = self::clean($row['type'] ?? null);
        if ($type === null) {
            $this->report['skipped']++;
            $this->message($line, 'error', __('import.errors.no_type'));

            return;
        }
        $brand = self::clean($row['brand'] ?? null);
        $number = self::clean($row['number'] ?? null);
        $item = $number ? SalesItem::where('number', strtoupper($number))->first() : null;
        $item ??= SalesItem::whereRaw('lower(type) = ?', [Str::lower($type)])
            ->when($brand, fn ($q) => $q->whereRaw('lower(brand) = ?', [Str::lower($brand)]), fn ($q) => $q->whereNull('brand'))->first();
        $isNew = $item === null;
        $item ??= new SalesItem(['number' => SalesItem::nextNumber(), 'unit' => 'piece', 'active' => true, 'list_price_cents' => 0, 'stock_qty' => 0]);

        // Category (and subcategory) must be in the sales lists; a category with one subcategory needs no subcategory column.
        $class = null;
        $catText = self::clean($row['category'] ?? null);
        $subText = self::clean($row['subcategory'] ?? null);
        $tags = array_values(array_filter(array_map('trim', preg_split('/[,;]/', (string) ($row['tags'] ?? '')))));
        if ($catText !== null || $tags) {
            $category = $catText !== null ? Classification::findCategory($catText, SalesItem::CATALOGUE) : $item->productCategory;
            if ($catText !== null && ! $category) {
                $this->report['skipped']++;
                $this->message($line, 'error', __('import.errors.unknown_category', ['value' => $catText]));

                return;
            }
            $active = $category ? $category->subcategories->where('active', true) : collect();
            $sub = $subText !== null ? ($category ? Classification::findSubcategory($category, $subText) : null) : ($active->count() === 1 ? $active->first() : $item->productSubcategory);
            if ($category && ! $sub) {
                $this->report['skipped']++;
                $this->message($line, 'error', $subText !== null ? __('import.errors.unknown_subcategory', ['value' => $subText]) : __('products.errors.subcategory_required'));

                return;
            }
            try {
                $class = Classification::check($sub?->category_id, $sub?->id, [], $tags, catalogue: SalesItem::CATALOGUE);
            } catch (ValidationException $e) {
                $this->report['skipped']++;
                $this->message($line, 'error', implode(' ', $e->validator->errors()->all()));

                return;
            }
        }
        $unit = self::clean($row['unit'] ?? null);
        if ($unit && ! in_array(Str::lower($unit), SalesItem::UNITS, true)) {
            $this->message($line, 'warning', __('import.warnings.unit', ['value' => $unit]));
            $unit = null;
        }
        $values = array_filter([
            'brand' => $brand, 'type' => Str::limit($type, 120, ''), 'description' => ($d = self::clean($row['description'] ?? null)) ? Str::limit($d, 300, '') : null,
            'unit' => $unit ? Str::lower($unit) : null, 'ean' => ($e = self::clean($row['ean'] ?? null)) ? Str::limit($e, 32, '') : null,
        ], fn ($v) => $v !== null);
        foreach (['list_price' => 'list_price_cents'] as $col => $field) {
            if (($p = self::amount($row[$col] ?? null)) !== null) {
                $values[$field] = (int) round($p * 100);
            } elseif (! empty($row[$col])) {
                $this->message($line, 'warning', __('import.warnings.price', ['value' => $row[$col]]));
            }
        }
        foreach (['stock' => 'stock_qty', 'min_stock' => 'min_stock'] as $col => $field) {
            if (($n = self::amount($row[$col] ?? null)) !== null) {
                $values[$field] = max(0, (int) round($n));
            }
        }
        $item->fill($values);
        if ($class) {
            $item->placeIn($class['sub']);
            if (($w = self::clean($row['search_words'] ?? null)) !== null) {
                $item->search_words = Str::limit($w, 1000, '');
            }
        }
        $changed = $item->isDirty();
        $item->save();
        if ($class && $class['tags']) {
            $changed = (bool) $item->tags()->syncWithoutDetaching($class['tags'])['attached'] || $changed;
        }

        // Up to three suppliers with their article number, discount and delivery time.
        $pref = (int) (self::amount($row['preferred'] ?? null) ?? 0);
        foreach ([1, 2, 3] as $n) {
            $name = self::clean($row['s'.$n] ?? null);
            $vat = $n === 1 ? VatNumber::normalize($row['supplier_vat'] ?? null) : null;
            if (! $name && ! $vat) {
                continue;
            }
            $supplier = $vat ? Company::where('vat_number', $vat)->first() : null;
            $supplier ??= $name ? Company::whereRaw('lower(name) = ?', [Str::lower($name)])->first() : null;
            if (! $supplier) {
                $this->message($line, 'warning', __('import.warnings.supplier', ['name' => $name ?? $vat]));

                continue;
            }
            $preferred = $pref === $n || ($pref === 0 && $item->suppliers()->count() === 0);
            if ($preferred) {
                $item->suppliers()->newPivotQuery()->update(['preferred' => false]);
            }
            $item->suppliers()->syncWithoutDetaching([$supplier->id => array_filter([
                'supplier_ref' => self::clean($row['s'.$n.'_ref'] ?? null),
                'discount_bp' => ($p = self::amount($row['s'.$n.'_discount'] ?? null)) !== null ? (int) round(min(100, max(0, $p)) * 100) : null,
                'lead_days' => ($d = self::amount($row['s'.$n.'_lead'] ?? null)) !== null ? (int) min(365, max(0, $d)) : null,
                'preferred' => $preferred ?: null,
            ], fn ($v) => $v !== null)]);
            if (! $supplier->is_supplier) {
                $supplier->forceFill(['is_supplier' => true])->save();
            }
            $changed = true;
        }

        $this->report[$isNew ? 'created' : ($changed ? 'updated' : 'skipped')]++;
    }

    /** The template's example rows use the "Demo …" suppliers. */
    protected function isExample(array $row): bool
    {
        return str_starts_with((string) ($row['s1'] ?? ''), 'Demo ');
    }

    private static function clean(?string $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }

    /** "119", "1.119,00", "13,5", "€ 65", "35%" → float. */
    private static function amount(?string $v): ?float
    {
        $v = trim(str_replace(['€', '%', ' ', "\u{00A0}"], '', (string) $v));
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
