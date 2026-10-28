<?php

namespace App\Models\Website;

use App\Models\Sales\IncomingMessage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A conversation with Clukie on the sales pages; the team takes over after a handover. */
#[Fillable(['token', 'visitor_uuid', 'customer_account_id', 'locale', 'status', 'name', 'email', 'phone', 'page', 'incoming_message_id', 'staff_user_id', 'handed_over_at', 'last_message_at', 'company_id', 'contact_id'])]
#[Hidden(['token'])]
class Chat extends Model
{
    protected $table = 'web_chats';

    public const STATUSES = ['bot', 'waiting', 'staff', 'closed'];

    protected function casts(): array
    {
        return ['handed_over_at' => 'datetime', 'last_message_at' => 'datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'chat_id')->orderBy('id');
    }

    public function incoming(): BelongsTo
    {
        return $this->belongsTo(IncomingMessage::class, 'incoming_message_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Contact::class);
    }

    /** Add a line to the conversation. */
    public function say(string $role, string $body, ?array $meta = null, ?int $userId = null): ChatMessage
    {
        $m = $this->messages()->create(['role' => $role, 'body' => $body, 'meta' => $meta, 'user_id' => $userId, 'created_at' => now()]);
        $this->forceFill(['last_message_at' => now()])->save();

        return $m;
    }
}
