<?php

namespace App\Models\Help;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "Still stuck?": a question from a colleague for the admin, with the answer. */
#[Fillable(['user_id', 'question', 'page', 'status', 'answer', 'answered_by_id', 'answered_at', 'article_id'])]
class Question extends Model
{
    protected $table = 'help_questions';

    protected function casts(): array
    {
        return ['answered_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
