<?php

namespace App\Models\Warehouse;

use App\Models\Rental\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The return costs of one order: reviewed by the account owner, the customer informed, invoiced after the deadline. */
#[Fillable(['order_id', 'status', 'token', 'sent_to', 'sent_at', 'sent_by_id', 'deadline', 'objection', 'objected_at', 'closed_at'])]
class ChargeNotice extends Model
{
    protected $table = 'warehouse_charge_notices';

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'deadline' => 'date', 'objected_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_id');
    }
}
