<?php

namespace App\Models\Storage;

use App\Models\Finance\PreInvoice;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One month of a storage contract, sent to pre-invoicing. */
#[Fillable(['contract_id', 'period_start', 'period_end', 'pre_invoice_id'])]
class StorageStatement extends Model
{
    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(StorageContract::class, 'contract_id');
    }

    public function preInvoice(): BelongsTo
    {
        return $this->belongsTo(PreInvoice::class);
    }
}
