<?php

namespace App\Models\Purchasing;

use App\Models\Company;
use App\Models\ProductSales\SalesQuote;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** A price request (PQ) to every supplier of the items, to compare and choose. */
#[Fillable(['number', 'status', 'sales_quote_id', 'needed_by', 'note', 'created_by_id', 'sent_at'])]
class PurchaseRequest extends Model
{
    protected function casts(): array
    {
        return ['needed_by' => 'date', 'sent_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RequestLine::class)->orderBy('id');
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'purchase_request_suppliers')->withPivot(['id', 'sent_at', 'replied_at'])->withTimestamps()->orderBy('name');
    }

    public function salesQuote(): BelongsTo
    {
        return $this->belongsTo(SalesQuote::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function repliedCount(): int
    {
        return $this->suppliers->filter(fn ($s) => $s->pivot->replied_at)->count();
    }

    /** Waiting longer than 5 days for a supplier. */
    public function isLate(): bool
    {
        return $this->status === 'sent' && $this->sent_at && $this->sent_at->lt(now()->subDays(5)) && $this->repliedCount() < $this->suppliers->count();
    }

    public static function nextNumber(string $prefix = 'PQ'): string
    {
        $table = $prefix === 'PQ' ? 'purchase_requests' : 'purchase_orders';
        $p = $prefix.'-'.now()->year.'-';
        $last = DB::table($table)->where('number', 'like', $p.'%')->pluck('number')->map(fn ($n) => (int) substr($n, strlen($p)))->max() ?? 0;

        return $p.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }
}
