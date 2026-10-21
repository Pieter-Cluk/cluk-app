<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** An office wall screen (planning or today) with its secret display link. */
#[Fillable(['view', 'token', 'locale', 'location', 'last_seen_at'])]
class WallScreen extends Model
{
    public const VIEWS = ['planning', 'today'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function link(): string
    {
        return route('wall.show', ['view' => $this->view, 'key' => $this->token]);
    }

    /** Seen in the last few minutes = the screen is on. */
    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(3));
    }
}
