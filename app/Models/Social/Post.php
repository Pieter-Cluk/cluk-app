<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['account_id', 'external_id', 'published_at', 'kind', 'text', 'permalink', 'thumb_url', 'views', 'reach', 'likes', 'comments', 'shares', 'saves', 'clicks', 'plan_id'])]
class Post extends Model
{
    protected $table = 'social_posts';

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /** Likes, comments, shares, saves and clicks together. */
    public function engagement(): int
    {
        return (int) $this->likes + (int) $this->comments + (int) $this->shares + (int) $this->saves + (int) $this->clicks;
    }
}
