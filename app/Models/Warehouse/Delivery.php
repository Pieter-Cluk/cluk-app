<?php

namespace App\Models\Warehouse;

use App\Models\Afrit6\ServiceRequest;
use App\Models\Member;
use App\Models\Rental\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Goods that arrived without anyone announcing them. */
#[Fillable(['user_id', 'photos', 'colli', 'pallets', 'damaged', 'carrier', 'label_text', 'match', 'member_id', 'order_id', 'purchase_order_id', 'request_id', 'status', 'closed_by_id', 'closed_at', 'note'])]
class Delivery extends Model
{
    protected $table = 'warehouse_deliveries';

    public const MATCHES = ['member', 'purchase', 'return', 'unknown'];

    protected function casts(): array
    {
        return ['photos' => 'array', 'damaged' => 'boolean', 'closed_at' => 'datetime', 'colli' => 'integer', 'pallets' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }

    /** Nobody claimed it after 2 working days: the office gets a reminder. */
    public function isOverdue(): bool
    {
        if ($this->status !== 'open') {
            return false;
        }
        $d = $this->created_at->copy();
        $n = 0;
        while ($n < 2) {
            $d->addDay();
            if (! $d->isWeekend()) {
                $n++;
            }
        }

        return now()->gte($d);
    }
}
