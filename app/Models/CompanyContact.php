<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/** The link between a company and a contact. When a contact joins a company, their numbers get that company's country code. */
class CompanyContact extends Pivot
{
    protected $table = 'company_contact';

    public $incrementing = true;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::created(function (CompanyContact $link) {
            $contact = Contact::find($link->contact_id);
            // Numbers typed locally (0…) before the contact had a company are written internationally now.
            if ($contact && (($contact->phone && ! $contact->phone_key) || ($contact->mobile && ! $contact->mobile_key))) {
                $contact->save();
            }
        });
    }
}
