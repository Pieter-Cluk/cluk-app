<?php

namespace App\Models\Sales;

use App\Models\Equipment\Item;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A line on a quote: an item from the catalogue, a free charge (transport…), or a text line. */
#[Fillable(['quote_id', 'position', 'kind', 'heading', 'item_id', 'labour_type_id', 'number', 'name', 'note', 'serial', 'qty', 'unit_price_cents', 'factor', 'discount_bp', 'amount_cents'])]
class QuoteLine extends Model
{
    public const KINDS = ['item', 'charge', 'text'];

    protected function casts(): array
    {
        return ['heading' => 'boolean', 'qty' => 'decimal:2', 'factor' => 'decimal:2', 'unit_price_cents' => 'integer', 'discount_bp' => 'integer', 'amount_cents' => 'integer', 'position' => 'integer'];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function labourType(): BelongsTo
    {
        return $this->belongsTo(LabourType::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** Partner prices asked for this line while making the quote. */
    public function subhireLines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Rental\SubhireLine::class);
    }

    protected static function booted(): void
    {
        // Services are net prices: never a line discount.
        static::saving(function (self $line) {
            if ($line->discount_bp && $line->isNet()) {
                $line->discount_bp = 0;
            }
        });
    }

    /** A service item (Afrit 6, Warehouse, Cluk Factory…): a net price, no line or project discount. */
    public function isNet(): bool
    {
        return $this->kind === 'item' && $this->item_id && $this->item?->kind === 'service';
    }

    /** A repair (service in sub-group Repair): always its own line, for one piece with its serial number. */
    public function isRepair(): bool
    {
        return $this->kind === 'item' && $this->item?->kind === 'service' && strcasecmp((string) $this->item->subcategory, 'Repair') === 0;
    }

    /** The piece this repair is for, when the serial number or piece code is known in Dry-hire. */
    public function repairedAsset(): ?\App\Models\Equipment\Asset
    {
        return $this->serial ? once(fn () => \App\Models\Equipment\Asset::with('item')->where('serial_number', $this->serial)->orWhere('code', strtoupper($this->serial))->first()) : null;
    }

    /** Does the rental factor apply? Only to equipment priced per day. */
    public function usesFactor(): bool
    {
        return $this->kind === 'item' && (! $this->item || in_array($this->item->price_unit ?? 'day', config('rental.factor_units'), true));
    }

    public function effectiveFactor(float $default): float
    {
        if (! $this->usesFactor()) {
            return $this->factor !== null ? (float) $this->factor : 1.0;
        }

        return $this->factor !== null ? (float) $this->factor : $default;
    }

    public function computeAmount(float $defaultFactor): int
    {
        if ($this->kind === 'text') {
            return 0;
        }

        return (int) round((float) $this->qty * $this->unit_price_cents * $this->effectiveFactor($defaultFactor) * (1 - ($this->isNet() ? 0 : $this->discount_bp) / 10000));
    }

    /** The name with the serial number and the extra information on one line (mails, pre-invoice). */
    public function nameWithNote(): string
    {
        $note = trim(preg_replace('/\s+/', ' ', (string) $this->note));
        $name = (string) $this->name.($this->serial ? ' · '.__('quotes.repair.sn').' '.$this->serial : '');

        return $note === '' ? $name : $name.' — '.$note;
    }

    /** Qty as shown: 12 instead of 12.00. */
    public function qtyLabel(): string
    {
        return rtrim(rtrim(number_format((float) $this->qty, 2, '.', ''), '0'), '.');
    }
}
