<?php

namespace App\Models\Media;

use App\Models\Contact;
use App\Models\Member;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One person on the list of a newsletter or promotion: sent once, never twice. */
#[Fillable(['message_id', 'email', 'contact_id', 'member_id', 'locale', 'status', 'sent_at'])]
class MessageSend extends Model
{
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
