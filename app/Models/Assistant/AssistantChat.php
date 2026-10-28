<?php

namespace App\Models\Assistant;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A colleague's conversation with Clukie in the platform (only that colleague sees it). */
#[Fillable(['token', 'user_id', 'locale', 'last_message_at'])]
#[Hidden(['token'])]
class AssistantChat extends Model
{
    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class, 'chat_id')->orderBy('id');
    }

    /** role: visitor (the colleague, as the chat window calls it) or clukie. */
    public function say(string $role, string $body): AssistantMessage
    {
        $m = $this->messages()->create(['role' => $role, 'body' => $body, 'created_at' => now()]);
        $this->forceFill(['last_message_at' => now()])->save();

        return $m;
    }
}
