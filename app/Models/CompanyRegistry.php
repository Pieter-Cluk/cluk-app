<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What the official company register (KBO/BCE) says about a company. */
#[Fillable(['company_id', 'source', 'enterprise_number', 'found', 'status', 'situation_code', 'situation', 'form_code', 'form', 'start_date', 'official_name', 'address', 'snapshot_date', 'checked_at'])]
class CompanyRegistry extends Model
{
    protected $table = 'company_registry';

    protected function casts(): array
    {
        return ['found' => 'boolean', 'situation' => 'array', 'form' => 'array', 'start_date' => 'date', 'snapshot_date' => 'date', 'checked_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** A description from the register in the app language (the register has NL, FR, DE and sometimes EN). */
    public function text(string $field, ?string $locale = null): ?string
    {
        $v = $this->{$field} ?? [];
        $locale ??= app()->getLocale();
        foreach ([$locale, $locale === 'es' ? 'en' : null, 'en', 'nl', 'fr'] as $l) {
            if ($l && ! empty($v[$l])) {
                return $v[$l];
            }
        }

        return null;
    }

    public function isActive(): bool
    {
        return $this->found && $this->status === 'AC';
    }

    public function isNormal(): bool
    {
        return in_array($this->situation_code, [null, '', '000'], true);
    }

    /** The public KBO page of this enterprise. */
    public function publicUrl(): string
    {
        return 'https://kbopub.economie.fgov.be/kbopub/zoeknummerform.html?nummer='.preg_replace('/\D/', '', $this->enterprise_number).'&actionLu=Zoek';
    }

    /** "BE0123456789" → "0123.456.789"; null when it is not a Belgian enterprise number. */
    public static function numberFromVat(?string $vat): ?string
    {
        $d = preg_replace('/\D/', '', (string) $vat);
        if (! preg_match('/^BE/i', trim((string) $vat)) || strlen($d) !== 10 && strlen($d) !== 9) {
            return null;
        }
        $d = str_pad($d, 10, '0', STR_PAD_LEFT);

        return substr($d, 0, 4).'.'.substr($d, 4, 3).'.'.substr($d, 7, 3);
    }
}
