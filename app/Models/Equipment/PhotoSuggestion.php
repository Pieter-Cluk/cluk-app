<?php

namespace App\Models\Equipment;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Photos found on the web for one product (one or more items), waiting for a person to pick one. */
#[Fillable(['group_key', 'title', 'item_numbers', 'candidates', 'files', 'page', 'source', 'note', 'status', 'chosen', 'decided_by_id', 'decided_at'])]
class PhotoSuggestion extends Model
{
    protected $table = 'equipment_photo_suggestions';

    public const STATUSES = ['pending', 'approved', 'rejected', 'failed'];

    protected function casts(): array
    {
        return ['item_numbers' => 'array', 'candidates' => 'array', 'files' => 'array', 'decided_at' => 'datetime', 'chosen' => 'integer'];
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    /** Downloaded copies that worked: [index => path]. */
    public function usable(): array
    {
        return array_filter($this->files ?? [], fn ($f) => is_string($f) && $f !== '');
    }

    public function items()
    {
        return Item::whereIn('number', $this->item_numbers ?? [])->get();
    }
}
