<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An extra (delivery) address of a company: warehouse, event site… */
#[Fillable(['label', 'street', 'postcode', 'city', 'country'])]
class CompanyAddress extends Model
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function line(): string
    {
        return trim(implode(', ', array_filter([$this->street, trim($this->postcode.' '.$this->city), $this->country !== 'BE' ? $this->country : null])));
    }
}
