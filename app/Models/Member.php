<?php

namespace App\Models;

use App\Notifications\MemberResetPassword;
use App\Support\VatNumber;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * An Afrit 6 co-working member with a login to the member portal.
 * Flow: register → confirm email → CLUK approves (company linked) → active.
 */
#[Fillable([
    'first_name', 'last_name', 'email', 'mobile', 'password', 'locale', 'newsletter',
    'reg_country', 'reg_vat_number', 'reg_company_name', 'reg_address', 'vies_status',
])]
#[Hidden(['password', 'remember_token'])]
class Member extends Authenticatable implements CanResetPasswordContract, HasLocalePreference
{
    use CanResetPassword, HasFactory, Notifiable;

    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const DECLINED = 'declined';

    public const BLOCKED = 'blocked';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'approved_at' => 'datetime',
            'last_login_at' => 'datetime',
            'newsletter' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    protected function initials(): Attribute
    {
        return Attribute::get(fn () => mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1)));
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function companyName(): string
    {
        return $this->company?->name ?? (string) $this->reg_company_name;
    }

    public function formattedRegVat(): string
    {
        return VatNumber::format($this->reg_vat_number);
    }

    public function preferredLocale(): string
    {
        return $this->locale;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new MemberResetPassword($token));
    }
}
