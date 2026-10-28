<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The late-payment decision for one invoice: invoice the interest now, when the customer pays, or skip. */
#[Fillable(['pre_invoice_id', 'decision', 'reason', 'interest_cents', 'fixed_cents', 'days', 'pre_invoice_made_id', 'decided_by_id', 'decided_at'])]
class Reminder extends Model
{
    protected $table = 'pre_invoice_reminders';

    public const DECISIONS = ['now', 'on_payment', 'skip'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'interest_cents' => 'integer', 'fixed_cents' => 'integer', 'days' => 'integer'];
    }

    public function preInvoice(): BelongsTo
    {
        return $this->belongsTo(PreInvoice::class);
    }

    /** The interest proforma made from this decision. */
    public function interestInvoice(): BelongsTo
    {
        return $this->belongsTo(PreInvoice::class, 'pre_invoice_made_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }
}
