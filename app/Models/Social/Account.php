<?php

namespace App\Models\Social;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A connected social media account (a Facebook page, an Instagram account, a LinkedIn page, a Google Business Profile…). */
#[Fillable(['platform', 'external_id', 'name', 'handle', 'url', 'avatar_url', 'token', 'refresh_token', 'token_expires_at', 'meta', 'status', 'last_error', 'last_synced_at', 'posts_synced_at', 'connected_by_id'])]
#[Hidden(['token', 'refresh_token'])]
class Account extends Model
{
    protected $table = 'social_accounts';

    public const PLATFORMS = ['facebook', 'instagram', 'linkedin', 'google', 'youtube', 'tiktok', 'threads'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'refresh_token' => 'encrypted', 'token_expires_at' => 'datetime', 'meta' => 'array',
            'last_synced_at' => 'datetime', 'posts_synced_at' => 'datetime'];
    }

    public function daily(): HasMany
    {
        return $this->hasMany(Daily::class, 'account_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'account_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'account_id');
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_id');
    }

    public function canPublish(): bool
    {
        return $this->status === 'connected' && (bool) config('social.platforms.'.$this->platform.'.publish');
    }

    public function label(): string
    {
        return __('social.platforms.'.$this->platform).' · '.$this->name;
    }
}
