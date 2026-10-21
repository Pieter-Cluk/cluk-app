<?php

namespace App\Models\Purchasing;

use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A supplier's answer on one line: net price and delivery date to us. */
#[Fillable(['purchase_request_line_id', 'company_id', 'net_cents', 'delivery_date'])]
class Reply extends Model
{
    protected $table = 'purchase_request_replies';

    protected function casts(): array
    {
        return ['net_cents' => 'integer', 'delivery_date' => 'date'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
