<?php

namespace App\Models\Web;

use App\Models\Company;
use App\Models\Contact;
use App\Models\ProductSales\SalesQuote;
use App\Models\Sales\IncomingMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** A customer's website login (one login = a dry-hire basket and a sales basket). */
#[Fillable(['email', 'password', 'first_name', 'last_name', 'company_name', 'vat_number', 'phone', 'locale', 'status', 'contact_id', 'company_id',
    'approved_by_id', 'approved_at', 'blocked_reason', 'email_verified_at', 'last_login_at', 'role', 'invited_by_id'])]
#[Hidden(['password', 'remember_token'])]
class CustomerAccount extends Authenticatable
{
    use Notifiable;

    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const BLOCKED = 'blocked';

    /** Main contact (manages the colleagues of the company), may order, or may only look. */
    public const ROLES = ['main', 'order', 'view'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'approved_at' => 'datetime', 'email_verified_at' => 'datetime', 'last_login_at' => 'datetime'];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function baskets(): HasMany
    {
        return $this->hasMany(Basket::class, 'account_id');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesQuote::class, 'customer_account_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(IncomingMessage::class, 'customer_account_id');
    }

    public function name(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function isMain(): bool
    {
        return $this->role === 'main' && $this->company_id !== null;
    }

    /** May send a dry-hire request or place an order (not a "look only" login). */
    public function canOrder(): bool
    {
        return $this->role !== 'view';
    }

    /** The other logins of the same company. */
    public function colleagues(): HasMany
    {
        return $this->hasMany(self::class, 'company_id', 'company_id')->whereKeyNot($this->id);
    }
}
