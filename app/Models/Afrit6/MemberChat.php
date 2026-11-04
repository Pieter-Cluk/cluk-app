<?php

namespace App\Models\Afrit6;

use App\Models\Member;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A member's conversation with Clukie in the Afrit 6 portal (only that member sees it). */
#[Fillable(['token', 'member_id', 'locale', 'last_message_at'])]
#[Hidden(['token'])]
class MemberChat extends Model
{
    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MemberChatMessage::class, 'chat_id')->orderBy('id');
    }

    /** role: visitor (the member, as the chat window calls it) or clukie. */
    public function say(string $role, string $body): MemberChatMessage
    {
        $m = $this->messages()->create(['role' => $role, 'body' => $body, 'created_at' => now()]);
        $this->forceFill(['last_message_at' => now()])->save();

        return $m;
    }
}
