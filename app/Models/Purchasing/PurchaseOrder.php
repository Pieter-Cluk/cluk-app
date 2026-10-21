<?php

namespace App\Models\Purchasing;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A purchase order (PO) to one supplier. */
#[Fillable(['number', 'purchase_request_id', 'company_id', 'status', 'their_ref', 'total_cents', 'invoice_cents', 'invoice_checked_at', 'sent_at', 'confirmed_at', 'received_at', 'created_by_id'])]
class PurchaseOrder extends Model
{
    public const STATUSES = ['sent', 'confirmed', 'received', 'cancelled'];

    protected function casts(): array
    {
        return ['total_cents' => 'integer', 'invoice_cents' => 'integer', 'invoice_checked_at' => 'datetime', 'sent_at' => 'datetime', 'confirmed_at' => 'datetime', 'received_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** Due: the last delivery date of its lines. */
    public function dueDate()
    {
        return $this->lines->max('delivery_date');
    }

    public function isLate(): bool
    {
        $due = $this->dueDate();

        return in_array($this->status, ['sent', 'confirmed'], true) && $due && $due->lt(today());
    }

    public function invoiceDiffers(): bool
    {
        return $this->invoice_cents !== null && $this->invoice_cents !== $this->total_cents && ! $this->invoice_checked_at;
    }
}
