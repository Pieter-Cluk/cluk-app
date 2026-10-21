<?php

namespace App\Imports;

use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;

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
        ];
    }

    protected function importRow(array $row, int $line): void
    {
        $number = strtoupper(trim((string) $row['number']));
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
            $brand = self::text($row['brand'] ?? null);
            $type = self::text($row['type'] ?? null);
            $values = array_filter([
                'category' => self::text($row['category'] ?? null),
                'subcategory' => self::text($row['subcategory'] ?? null),
                'unit' => self::text($row['unit'] ?? null),
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
                $item->name = $name !== '' ? $name : (self::text($row['description'] ?? null) ?? $number);
            }
            if ($d = self::text($row['description'] ?? null)) {
                $item->descriptions = array_merge($item->descriptions ?? [], ['en' => $d]);
            }
            if ($isNew) {
                $item->serial = $serial !== '';
            }
        }

        if ($serial !== '') {
            $item->serial = true;
            $changed = $item->isDirty();
            $item->save();
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
        $this->report[$isNew ? 'created' : ($changed ? 'updated' : 'skipped')]++;
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
