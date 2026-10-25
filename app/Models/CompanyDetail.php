<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** CLUK's own legal details and document defaults (always one row). */
#[Fillable([
    'legal_name', 'legal_form', 'vat_number', 'rpr', 'street', 'postcode', 'city', 'country',
    'warehouse_address', 'phone', 'email', 'invoice_email', 'website',
    'payment_term_days', 'quote_valid_days', 'option_days', 'footer_line',
    'vat_default_be', 'vat_default_eu_business', 'vat_default_eu_private', 'vat_default_outside_eu',
])]
class CompanyDetail extends Model
{
    public const LEGAL_FORMS = ['BV', 'NV', 'CommV', 'CV', 'VZW'];

    protected function casts(): array
    {
        return [
            'payment_term_days' => 'integer',
            'quote_valid_days' => 'integer',
            'option_days' => 'integer',
            'okioki_send' => 'boolean',
            'invoice_next' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->orderBy('id')->first()
            ?? static::create(['legal_name' => 'CLUK', 'country' => 'BE']);
    }

    /** Belgian enterprise number (KBO) follows from the VAT number: 1010.948.054. */
    public function enterpriseNumber(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $this->vat_number);

        return strlen($digits) === 10 ? substr($digits, 0, 4).'.'.substr($digits, 4, 3).'.'.substr($digits, 7) : null;
    }

    /** Peppol ID for Belgian companies: scheme 0208 + enterprise number. */
    public function peppolId(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $this->vat_number);

        return strlen($digits) === 10 ? '0208:'.$digits : null;
    }

    public function address(): string
    {
        return trim(implode(', ', array_filter([$this->street, trim($this->postcode.' '.$this->city)])));
    }
}
