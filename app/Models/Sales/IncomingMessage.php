<?php

namespace App\Models\Sales;

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A request that came in (website form, email, social, WhatsApp, phone, visit) and still has to be handled. */
#[Fillable([
    'channel', 'subject', 'body', 'from_name', 'from_email', 'from_phone', 'company_name', 'module',
    'company_id', 'contact_id', 'status', 'deal_id', 'handled_by_id', 'handled_at', 'handled_note', 'received_at',
])]
class IncomingMessage extends Model
{
    public const CHANNELS = ['website', 'email', 'linkedin', 'facebook', 'instagram', 'whatsapp', 'phone', 'visit', 'other'];

    public const STATUSES = ['new', 'converted', 'linked', 'asked', 'dismissed'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'handled_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_id');
    }

    /** Find a known contact (by email) and company (by contact, email domain or name). */
    public function matchKnown(): void
    {
        if ($this->from_email && ! $this->contact_id) {
            $contact = Contact::where('email', Str::lower($this->from_email))->first();
            $this->contact_id = $contact?->id;
            if ($contact && ! $this->company_id) {
                $this->company_id = $contact->companies()->first()?->id;
            }
        }
        if (! $this->company_id && $this->company_name) {
            $this->company_id = Company::whereRaw('lower(name) = ?', [Str::lower(trim($this->company_name))])->value('id');
        }
        if (! $this->company_id && $this->from_email && ! preg_match('/@(gmail|hotmail|outlook|live|yahoo|icloud|telenet|skynet|proximus)\./i', $this->from_email)) {
            $domain = Str::after(Str::lower($this->from_email), '@');
            $this->company_id = Company::whereRaw('lower(email) like ?', ['%@'.$domain])->orWhereRaw('lower(website) like ?', ['%'.$domain.'%'])->value('id');
        }
    }
}
