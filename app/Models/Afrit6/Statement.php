<?php

namespace App\Models\Afrit6;

use App\Models\Company;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A contract-month statement for one member, saved as a proforma for pre-invoicing. */
#[Fillable(['member_id', 'company_id', 'period_start', 'period_end', 'lines', 'total_cents', 'status', 'created_by_id'])]
class Statement extends Model
{
    protected $table = 'afrit6_statements';

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'lines' => 'array', 'total_cents' => 'integer'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
