<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One account a planned post goes to, with its own text (optional) and result. */
#[Fillable(['plan_id', 'account_id', 'body', 'status', 'container_id', 'external_id', 'permalink', 'error', 'tries', 'published_at'])]
class PlanTarget extends Model
{
    protected $table = 'social_plan_targets';

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'tries' => 'integer'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /** The text for this account: its own, or the plan's, with the tracked link at the end where the platform shows no link card. */
    public function text(): string
    {
        $body = trim($this->body ?: $this->plan->body);
        $link = $this->plan->trackedLink($this->account->platform);
        if ($link && in_array($this->account->platform, ['instagram'], true)) {
            return $body; // Instagram doesn't make links in captions clickable: the link goes in the profile.
        }
        if ($link && ! in_array($this->account->platform, ['facebook', 'linkedin'], true)) {
            return $body."\n\n".$link;
        }

        return $body;
    }
}
