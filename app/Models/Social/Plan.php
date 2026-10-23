<?php

namespace App\Models\Social;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A post made in CLUK, to be published on one or more accounts. */
#[Fillable(['title', 'body', 'link_url', 'media', 'scheduled_at', 'status', 'created_by_id', 'scheduled_by_id'])]
class Plan extends Model
{
    protected $table = 'social_plans';

    public const STATUSES = ['draft', 'scheduled', 'publishing', 'done', 'partly', 'failed'];

    protected function casts(): array
    {
        return ['media' => 'array', 'scheduled_at' => 'datetime'];
    }

    public function targets(): HasMany
    {
        return $this->hasMany(PlanTarget::class, 'plan_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by_id');
    }

    public function editable(): bool
    {
        return in_array($this->status, ['draft', 'scheduled'], true);
    }

    public function images(): array
    {
        return array_values(array_filter($this->media ?? [], fn ($m) => ($m['type'] ?? '') === 'image'));
    }

    public function video(): ?array
    {
        return collect($this->media ?? [])->firstWhere('type', 'video');
    }

    /** The link with tracking codes for one platform, so Management › Website shows what the post brought in. */
    public function trackedLink(string $platform): ?string
    {
        if (! $this->link_url) {
            return null;
        }
        $parts = parse_url($this->link_url);
        parse_str($parts['query'] ?? '', $q);
        $q += ['utm_source' => $platform, 'utm_medium' => 'social', 'utm_campaign' => Str::slug($this->title) ?: 'post-'.$this->id];
        $base = strtok($this->link_url, '?#');

        return $base.'?'.http_build_query($q).(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
