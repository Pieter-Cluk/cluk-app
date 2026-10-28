<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line in a Clukie chat: visitor, clukie, staff or note. meta: product cards shown with it. */
#[Fillable(['chat_id', 'role', 'body', 'meta', 'user_id', 'created_at'])]
class ChatMessage extends Model
{
    protected $table = 'web_chat_messages';

    public const ROLES = ['visitor', 'clukie', 'staff', 'note'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class, 'chat_id');
    }

    /** What the browser gets. */
    public function forWeb(): array
    {
        return ['id' => $this->id, 'role' => $this->role, 'body' => $this->body, 'cards' => $this->meta['cards'] ?? [], 'offer' => ! empty($this->meta['offer_handover']), 'at' => $this->created_at?->toIso8601String()];
    }
}
