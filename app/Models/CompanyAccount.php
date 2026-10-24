<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One financial year of a company's annual accounts at the National Bank. Money in cents. */
#[Fillable(['company_id', 'year', 'period_end', 'reference', 'model', 'deposited_on', 'currency', 'turnover', 'gross_margin', 'operating_result', 'profit', 'equity', 'total_assets', 'employees', 'period'])]
class CompanyAccount extends Model
{
    public const FIELDS = ['turnover', 'gross_margin', 'operating_result', 'profit', 'equity', 'total_assets', 'employees'];

    protected function casts(): array
    {
        return ['period_end' => 'date', 'deposited_on' => 'date', 'employees' => 'float', 'year' => 'integer'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
