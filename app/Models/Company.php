<?php

namespace App\Models;

use App\Support\VatNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A customer, a supplier, or both. */
#[Fillable([
    'name', 'legal_form', 'is_customer', 'is_supplier', 'vat_number', 'country', 'street', 'postcode', 'city',
    'phone', 'email', 'website', 'language', 'account_owner_id', 'vat_regime',
    'customer_payment_days', 'supplier_payment_days', 'supplies', 'notes', 'po_required',
])]
class Company extends Model
{
    use HasFactory;

    /** What a supplier can supply (filters and the supplier list). */
    public const SUPPLIES = ['subhire', 'sales_items', 'transport', 'rigging', 'consumables', 'services'];

    public const LEGAL_FORMS = ['BV', 'NV', 'CommV', 'CV', 'VZW', 'VOF', 'SRL', 'SA', 'ASBL', 'GmbH', 'Ltd', 'SL', 'SAS'];

    protected function casts(): array
    {
        return [
            'is_customer' => 'boolean',
            'is_supplier' => 'boolean',
            'po_required' => 'boolean',
            'supplies' => 'array',
            'vies_checked_at' => 'datetime',
            'archived_at' => 'datetime',
            'accounts_checked_at' => 'datetime',
            'customer_payment_days' => 'integer',
            'supplier_payment_days' => 'integer',
        ];
    }

    public function accountOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_owner_id');
    }

    public function registry(): HasOne
    {
        return $this->hasOne(CompanyRegistry::class);
    }

    /** Annual accounts at the National Bank, newest year first. */
    public function accounts(): HasMany
    {
        return $this->hasMany(CompanyAccount::class)->orderByDesc('year');
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class)->withPivot(['id', 'job_title', 'is_main', 'is_billing']);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CompanyAddress::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /** all, customers, suppliers, both */
    public function scopeRelationship(Builder $query, string $view): Builder
    {
        return match ($view) {
            'customers' => $query->where('is_customer', true),
            'suppliers' => $query->where('is_supplier', true),
            'both' => $query->where('is_customer', true)->where('is_supplier', true),
            default => $query,
        };
    }

    /** customer, supplier or both — also the colour on screen. */
    public function relationshipKey(): string
    {
        return match (true) {
            $this->is_customer && $this->is_supplier => 'both',
            $this->is_supplier => 'supplier',
            default => 'customer',
        };
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim(preg_replace('/\b(BV|NV|BVBA|VZW|CommV|SRL|SA|GmbH|Ltd|SL|SAS)\b\.?/i', '', $this->name))) ?: [];

        return mb_strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice(array_filter($words), 0, 2)))) ?: '?';
    }

    public function formattedVat(): string
    {
        return VatNumber::format($this->vat_number);
    }

    public function address(): string
    {
        return trim(implode(', ', array_filter([$this->street, trim($this->postcode.' '.$this->city), $this->country !== 'BE' ? $this->country : null])));
    }

    // ------------------------------------------------------------------ VAT

    /** Which kind of customer this is for VAT: be, eu_business, eu_private, outside_eu. */
    public function vatSituation(): string
    {
        return match (true) {
            $this->country === 'BE' => 'be',
            VatNumber::isEu($this->country) && $this->vies_status === 'valid' => 'eu_business',
            VatNumber::isEu($this->country) => 'eu_private',
            default => 'outside_eu',
        };
    }

    /** The OkiOki VAT code used on this company's documents. */
    public function vatCode(): string
    {
        if ($this->vat_regime !== null && $this->vat_regime !== 'auto') {
            return $this->vat_regime;
        }

        return (string) CompanyDetail::current()->{'vat_default_'.$this->vatSituation()};
    }

    public function vatRate(?string $date = null): ?VatRate
    {
        return VatRate::forCode($this->vatCode(), $date);
    }
}
