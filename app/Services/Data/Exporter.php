<?php

namespace App\Services\Data;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\ProductSales\SalesItem;
use App\Models\User;
use App\Support\SheetWriter;
use Illuminate\Support\Str;

/**
 * Settings › Import & export: the same four lists the import takes, with the
 * same column names, so an exported file can be changed in Excel and imported
 * again (rows are matched on number, VAT number, email…).
 * Purchase discounts and costs are only filled in for people who may see margins.
 */
class Exporter
{
    public const KINDS = ['companies', 'contacts', 'equipment', 'salesitems'];

    /** Sheet names: the same as in the import template, so the import recognises them. */
    public const SHEETS = ['companies' => 'Companies', 'contacts' => 'Contacts', 'equipment' => 'Equipment', 'salesitems' => 'Sales items'];

    public function __construct(private User $user, private bool $includeArchived = false) {}

    /** @return list<list<mixed>> header + rows */
    public function rows(string $kind): array
    {
        return match ($kind) {
            'companies' => $this->companies(),
            'contacts' => $this->contacts(),
            'equipment' => $this->equipment(),
            'salesitems' => $this->salesItems(),
        };
    }

    /** Writes the chosen lists to one workbook. @return array{0: string, 1: array<string, int>} [path, rows per kind] */
    public function workbook(array $kinds, string $path): array
    {
        $w = new SheetWriter;
        $counts = [];
        foreach (array_values(array_intersect(self::KINDS, $kinds)) as $kind) {
            $rows = $this->rows($kind);
            $counts[$kind] = count($rows) - 1;
            $w->add(self::SHEETS[$kind], $rows);
        }

        return [$w->save($path), $counts];
    }

    private static function money(?int $cents): ?float
    {
        return $cents === null ? null : round($cents / 100, 2);
    }

    private static function num($v): int|float|null
    {
        return $v === null || $v === '' ? null : (float) $v + 0;
    }

    private function companies(): array
    {
        $rows = [['Company name', 'VAT number', 'Legal form', 'Street', 'Postcode', 'City', 'Country', 'Phone', 'Email', 'Website', 'Relationship', 'Language', 'Payment terms (days)', 'Account owner', 'Notes']];
        $q = Company::with('accountOwner')->orderBy('name');
        if (! $this->includeArchived) {
            $q->active();
        }
        foreach ($q->cursor() as $c) {
            $rel = $c->is_customer && $c->is_supplier ? 'Both' : ($c->is_supplier ? 'Supplier' : 'Customer');
            $rows[] = [$c->name, $c->vat_number, $c->legal_form, $c->street, $c->postcode, $c->city, $c->country, $c->phone, $c->email, $c->website, $rel,
                $c->language ? strtoupper($c->language) : null, $c->is_supplier && ! $c->is_customer ? $c->supplier_payment_days : $c->customer_payment_days,
                $c->accountOwner?->name, $c->notes];
        }

        return $rows;
    }

    /** One row per contact and company (a contact at two companies is in it twice, as the import expects). */
    private function contacts(): array
    {
        $rows = [['First name', 'Last name', 'Company VAT number', 'Company name', 'Job title', 'Email', 'Phone', 'Mobile', 'Language', 'Newsletter consent']];
        foreach (Contact::with('companies')->orderBy('first_name')->orderBy('last_name')->cursor() as $p) {
            $base = fn ($c) => [$p->first_name, $p->last_name, $c?->vat_number, $c?->name, $c?->pivot->job_title, $p->email, $p->phone, $p->mobile,
                $p->language ? strtoupper($p->language) : null, $p->newsletter ? 'Yes' : 'No'];
            if ($p->companies->isEmpty()) {
                $rows[] = $base(null);
            }
            foreach ($p->companies as $c) {
                $rows[] = $base($c);
            }
        }

        return $rows;
    }

    /** Serial items: one row per piece (with its serial number); counted items: one row with the quantity. */
    private function equipment(): array
    {
        $attrs = ['length' => 'Catalogue length (m)', 'frequency_band' => 'Frequency band', 'frequency_range' => 'Frequency range', 'connector' => 'Connector', 'colour' => 'Colour', 'truss_series' => 'Truss series'];
        $rows = [array_merge(['Itemnumber', 'Name', 'Category', 'Sub-Category', 'Unit', 'Brand', 'Type', 'Description', 'Rental price per day', 'Catalog Price', 'Country of Origin', 'HS-Code',
            'Weight (kg)', 'Width (m)', 'Length (m)', 'Height (m)', 'Serial number', 'Quantity'], array_values($attrs), ['Tags', 'Search words', 'Piece code', 'Piece status'])];
        $q = Item::with(['productCategory', 'productSubcategory', 'options.attribute', 'tags', 'assets'])->orderBy('number');
        if (! $this->includeArchived) {
            $q->where('active', true);
        }
        foreach ($q->cursor() as $i) {
            $byAttr = $i->options->groupBy(fn ($o) => $o->attribute?->key)->map(fn ($os) => $os->pluck('value')->implode(', '));
            $base = [$i->number, $i->name, $i->productCategory?->label('en'), $i->productSubcategory?->label('en'), $i->unit, $i->brand, $i->type, $i->descriptions['en'] ?? null,
                self::money($i->day_price_cents), self::money($i->catalog_price_cents), $i->origin, $i->hs_code,
                self::num($i->weight_kg), self::num($i->width_m), self::num($i->length_m), self::num($i->height_m)];
            $tail = array_merge(array_map(fn ($k) => $byAttr[$k] ?? null, array_keys($attrs)), [$i->tags->map(fn ($t) => $t->label('en'))->implode(', '), $i->search_words]);
            $pieces = $i->serial ? $i->assets->filter(fn ($a) => $this->includeArchived || $a->status !== Asset::RETIRED)->sortBy('code') : collect();
            if ($pieces->isEmpty()) {
                $rows[] = array_merge($base, [null, $i->serial ? null : (int) $i->stock_qty], $tail, [null, null]);
            }
            foreach ($pieces as $a) {
                $rows[] = array_merge($base, [$a->serial_number ?: null, null], $tail, [$a->code, $a->status]);
            }
        }

        return $rows;
    }

    private function salesItems(): array
    {
        $cost = $this->user->hasRight('finance.see_margins');
        $rows = [['Item number', 'Brand', 'Type', 'Manufacturer code', 'Description', 'Category', 'Sub-Category', 'Unit', 'List price', 'Stock', 'Minimum stock', 'EAN / barcode',
            'Supplier 1', 'Supplier 1 article no.', 'Supplier 1 discount %', 'Supplier 1 lead time (working days)',
            'Supplier 2', 'Supplier 2 article no.', 'Supplier 2 discount %', 'Supplier 2 lead time (working days)',
            'Supplier 3', 'Supplier 3 article no.', 'Supplier 3 discount %', 'Supplier 3 lead time (working days)',
            'Preferred supplier', 'Tags', 'Search words', 'Purchase price (calculated)', 'Margin at list price (calculated)']];
        $q = SalesItem::with(['suppliers', 'productCategory', 'productSubcategory', 'tags'])->orderBy('number');
        if (! $this->includeArchived) {
            $q->where('active', true);
        }
        foreach ($q->cursor() as $i) {
            $sup = [];
            $pref = null;
            foreach ($i->suppliers->take(3)->values() as $n => $s) {
                $sup = array_merge($sup, [$s->name, $s->pivot->supplier_ref, $cost ? $s->pivot->discount_bp / 100 : null, (int) $s->pivot->lead_days]);
                $pref = $s->pivot->preferred ? $n + 1 : $pref;
            }
            $sup = array_pad($sup, 12, null);
            $c = $cost && $i->suppliers->isNotEmpty() ? $i->costCents() : null;
            $rows[] = array_merge([$i->number, $i->brand, $i->type, $i->manufacturer_code, $i->description, $i->productCategory?->label('en'), $i->productSubcategory?->label('en'),
                $i->unit, self::money($i->list_price_cents), (int) $i->stock_qty, (int) $i->min_stock, $i->ean], $sup,
                [$pref, $i->tags->map(fn ($t) => $t->label('en'))->implode(', '), $i->search_words,
                    self::money($c), $c !== null && $i->list_price_cents ? round(1 - $c / $i->list_price_cents, 3) : null]);
        }

        return $rows;
    }

    public static function fileName(array $kinds): string
    {
        $part = count($kinds) === 1 ? Str::slug(self::SHEETS[$kinds[0]] ?? 'data') : 'data';

        return 'CLUK_'.$part.'_'.now()->format('Y-m-d_Hi').'.xlsx';
    }
}
