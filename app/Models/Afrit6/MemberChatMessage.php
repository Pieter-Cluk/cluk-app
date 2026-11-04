<?php

namespace App\Models\Afrit6;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['chat_id', 'role', 'body', 'created_at'])]
class MemberChatMessage extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** What the chat window gets (same shape as on the website). */
    public function forWeb(): array
    {
        return ['id' => $this->id, 'role' => $this->role, 'body' => $this->body, 'cards' => [], 'offer' => false, 'at' => $this->created_at?->toIso8601String()];
    }
}
