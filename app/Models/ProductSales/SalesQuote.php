<?php

namespace App\Models\ProductSales;

use App\Models\Company;
use App\Models\CompanyDetail;
use App\Models\Contact;
use App\Models\Purchasing\PurchaseRequest;
use App\Models\Sales\Deal;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** A sale of goods: quote (S-QUO) → accepted → purchasing → order confirmation (S-OC) → delivered. */
#[Fillable([
    'number', 'status', 'company_id', 'contact_id', 'owner_id', 'deal_id', 'title', 'language', 'intro', 'notes', 'valid_until', 'delivery_mode',
    'vat_code', 'vat_rate_bp', 'subtotal_cents', 'cost_cents', 'net_cents', 'vat_cents', 'gross_cents', 'approved_by_id', 'approved_at', 'approved_sig',
    'approval_note', 'token', 'sent_at', 'sent_to', 'answered_at', 'answered_name', 'po_number', 'decline_reason', 'confirmed_at', 'delivered_at',
])]
class SalesQuote extends Model
{
    public const STATUSES = ['draft', 'sent', 'accepted', 'confirmed', 'delivered', 'declined', 'cancelled'];

    /** Below this margin a line needs the approval of someone with "Sales: approve low margin". */
    public const MIN_MARGIN_BP = 1000;

    protected function casts(): array
    {
        return [
            'valid_until' => 'date', 'approved_at' => 'datetime', 'sent_at' => 'datetime', 'answered_at' => 'datetime', 'confirmed_at' => 'datetime', 'delivered_at' => 'datetime',
            'vat_rate_bp' => 'integer', 'subtotal_cents' => 'integer', 'cost_cents' => 'integer', 'net_cents' => 'integer', 'vat_cents' => 'integer', 'gross_cents' => 'integer',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesQuoteLine::class)->orderBy('position')->orderBy('id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    public function code(): string
    {
        return in_array($this->status, ['confirmed', 'delivered'], true) ? str_replace('S-QUO', 'S-OC', $this->number) : $this->number;
    }

    public function docCode(string $prefix): string
    {
        return str_replace('S-QUO', $prefix, $this->number);
    }

    public function customerName(): string
    {
        return $this->company?->name ?? $this->contact?->name ?? '—';
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    /** Lines under the minimum margin. */
    public function lowLines()
    {
        return $this->lines->filter(fn (SalesQuoteLine $l) => $l->kind === 'item' && $l->marginBp() < self::MIN_MARGIN_BP);
    }

    /** What the approval is about: the lines as they are now. */
    public function signature(): string
    {
        return hash('sha256', $this->lines->map(fn ($l) => $l->id.':'.$l->qty.':'.$l->unit_cents)->implode('|'));
    }

    public function needsApproval(): bool
    {
        return $this->lowLines()->isNotEmpty() && $this->approved_sig !== $this->signature();
    }

    public function marginBp(): int
    {
        return $this->net_cents ? (int) round(($this->net_cents - $this->cost_cents) / $this->net_cents * 10000) : 0;
    }

    public function vatRateNow(): ?VatRate
    {
        return $this->company ? $this->company->vatRate(today()->toDateString()) : VatRate::forCode((string) CompanyDetail::current()->vat_default_be, today()->toDateString());
    }

    public function recalculate(): void
    {
        $sub = 0;
        $cost = 0;
        foreach ($this->lines()->get() as $l) {
            $l->unit_cents = $l->kind === 'text' ? 0 : (int) round($l->list_cents * (1 - $l->discount_bp / 10000));
            $l->amount_cents = $l->unit_cents * $l->qty;
            $l->save();
            $sub += $l->amount_cents;
            $cost += $l->cost_cents * $l->qty;
        }
        $rate = $this->vatRateNow();
        $vat = (int) round($sub * ($rate?->rate_bp ?? 0) / 10000);
        $this->forceFill(['subtotal_cents' => $sub, 'cost_cents' => $cost, 'net_cents' => $sub, 'vat_code' => $rate?->code, 'vat_rate_bp' => $rate?->rate_bp ?? 0,
            'vat_cents' => $vat, 'gross_cents' => $sub + $vat])->save();
    }

    public function vatPercent(): string
    {
        return rtrim(rtrim(number_format($this->vat_rate_bp / 100, 2, '.', ''), '0'), '.').'%';
    }

    /** All pieces to buy have a delivery date from a confirmed purchase order. */
    public function datesIn(): bool
    {
        return $this->lines->filter(fn ($l) => $l->kind === 'item' && $l->toBuy() > 0)->every(fn ($l) => $l->delivery_date !== null);
    }

    public static function nextNumber(): string
    {
        $prefix = 'S-QUO-'.now()->year.'-';
        $last = DB::table('sales_quotes')->where('number', 'like', $prefix.'%')->pluck('number')->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }
}
