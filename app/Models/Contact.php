<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A person at a customer or supplier. Can be linked to more than one company. */
#[Fillable(['first_name', 'last_name', 'email', 'phone', 'mobile', 'language', 'newsletter', 'notes'])]
class Contact extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['newsletter' => 'boolean', 'newsletter_changed_at' => 'datetime'];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withPivot(['id', 'job_title', 'is_main', 'is_billing']);
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
