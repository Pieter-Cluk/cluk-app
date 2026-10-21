<?php

namespace App\Models\Finance;

use App\Models\Company;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/** A proforma waiting for the accountant, then the invoice made in OkiOki. */
#[Fillable([
    'number', 'source_type', 'source_id', 'source_code', 'module', 'company_id', 'member_id', 'made_by_id', 'owner_id', 'description', 'status',
    'lines', 'vat_code', 'vat_rate_bp', 'net_cents', 'cost_cents', 'vat_cents', 'gross_cents', 'po_number', 'payment_days', 'service_date', 'checks',
    'override_by_id', 'back_note', 'sent_at', 'sent_by_id', 'invoice_number', 'invoice_date', 'due_date', 'paid_at',
])]
class PreInvoice extends Model
{
    public const STATUSES = ['check', 'back', 'sent', 'paid', 'cancelled'];

    public const MODULES = ['dryhire', 'factory', 'sales', 'afrit6', 'warehouse'];

    protected function casts(): array
    {
        return [
            'lines' => 'array', 'checks' => 'array', 'service_date' => 'date', 'invoice_date' => 'date', 'due_date' => 'date', 'sent_at' => 'datetime', 'paid_at' => 'datetime',
            'net_cents' => 'integer', 'cost_cents' => 'integer', 'vat_cents' => 'integer', 'gross_cents' => 'integer', 'vat_rate_bp' => 'integer', 'payment_days' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function madeBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'made_by_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_id');
    }

    public function overrideBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_by_id');
    }

    public function reminder(): HasOne
    {
        return $this->hasOne(Reminder::class);
    }

    public function customerName(): string
    {
        return $this->company?->name ?? $this->member?->companyName() ?? '—';
    }

    public function reds(): int
    {
        return collect($this->checks ?? [])->where('level', 'bad')->count();
    }

    public function ambers(): int
    {
        return collect($this->checks ?? [])->where('level', 'warn')->count();
    }

    public function isOverdue(): bool
    {
        return $this->status === 'sent' && $this->due_date && $this->due_date->lt(today());
    }

    public function daysLate(): int
    {
        return $this->isOverdue() ? (int) $this->due_date->diffInDays(today()) : 0;
    }

    public static function nextNumber(): string
    {
        $p = 'PF-'.now()->year.'-';
        $last = DB::table('pre_invoices')->where('number', 'like', $p.'%')->pluck('number')->map(fn ($n) => (int) substr($n, strlen($p)))->max() ?? 0;

        return $p.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }
}
