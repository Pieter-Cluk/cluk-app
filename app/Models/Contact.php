<?php

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A person at a customer or supplier. Can be linked to more than one company. */
#[Fillable(['first_name', 'last_name', 'email', 'phone', 'mobile', 'language', 'newsletter', 'notes', 'birth_date', 'birthday_card', 'birthday_sent_year', 'promo_opt_out'])]
class Contact extends Model
{
    protected static function booted(): void
    {
        // Phone and mobile in one international format (country of the main company, else Belgium), with keys to find callers by.
        static::saving(function (Contact $c) {
            if ($c->isDirty(['phone', 'mobile']) || ($c->phone && ! $c->phone_key) || ($c->mobile && ! $c->mobile_key)) {
                $country = $c->exists ? $c->companies()->orderByDesc('company_contact.is_main')->value('country') : null;
                foreach (['phone', 'mobile'] as $f) {
                    // A brand-new contact with a local number (0…): wait until it is linked to its company, which gives the country.
                    if (! $country && ! $c->exists && preg_match('/^\s*0(?!0)/', (string) $c->{$f})) {
                        continue;
                    }
                    $c->{$f} = Phone::international($c->{$f}, $country ?: 'BE');
                    $c->{$f.'_key'} = Phone::key($c->{$f}, $country ?: 'BE');
                }
            }
        });
    }

    /** The country used for this contact's numbers. */
    public function phoneCountry(): string
    {
        return ($this->relationLoaded('companies') ? $this->companies->sortByDesc('pivot.is_main')->first()?->country : $this->companies()->orderByDesc('company_contact.is_main')->value('country')) ?: 'BE';
    }

    use HasFactory;

    protected function casts(): array
    {
        return ['newsletter' => 'boolean', 'newsletter_changed_at' => 'datetime', 'birth_date' => 'date', 'birthday_card' => 'boolean', 'birthday_sent_year' => 'integer', 'promo_opt_out' => 'boolean'];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->using(CompanyContact::class)->withPivot(['id', 'job_title', 'is_main', 'is_billing']);
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    protected function initials(): Attribute
    {
        return Attribute::get(fn () => mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr((string) $this->last_name, 0, 1)));
    }
}
