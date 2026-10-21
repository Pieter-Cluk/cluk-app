<?php

namespace App\Models;

use App\Models\Afrit6\DeskRental;
use App\Models\Afrit6\RoomBooking;
use App\Models\Afrit6\ServiceRequest;
use App\Models\Afrit6\Statement;
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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * An Afrit 6 co-working member with a login to the member portal.
 * Flow: register → confirm email → CLUK approves (company linked) → active.
 */
#[Fillable([
    'first_name', 'last_name', 'email', 'mobile', 'password', 'locale', 'newsletter',
    'reg_country', 'reg_vat_number', 'reg_company_name', 'reg_address', 'vies_status',
    'terms_accepted_at', 'rules_accepted_at', 'payment_terms_accepted_at', 'terms_version',
])]
#[Hidden(['password', 'remember_token', 'door_code'])]
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
            'rules_accepted_at' => 'datetime',
            'payment_terms_accepted_at' => 'datetime',
            'approved_at' => 'datetime',
            'last_login_at' => 'datetime',
            'newsletter' => 'boolean',
            'door_code' => 'encrypted',
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

    public function deskRentals(): HasMany
    {
        return $this->hasMany(DeskRental::class)->orderBy('starts_on');
    }

    public function roomBookings(): HasMany
    {
        return $this->hasMany(RoomBooking::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function statements(): HasMany
    {
        return $this->hasMany(Statement::class)->orderByDesc('period_start');
    }

    /** Desks rented today. */
    public function desksToday()
    {
        return $this->deskRentals()->activeOn(today())->with('space')->get();
    }

    /** Desks of this member active on a day (for the free room slots of that week). */
    public function deskCountOn(Carbon $day): int
    {
        return $this->deskRentals()->activeOn($day)->count();
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

    /** Has the member accepted the current house & booking rules and payment terms? */
    public function hasCurrentTerms(): bool
    {
        return $this->terms_version === config('afrit6.terms_version')
            && $this->rules_accepted_at !== null && $this->payment_terms_accepted_at !== null;
    }

    public function acceptCurrentTerms(): void
    {
        $this->forceFill([
            'rules_accepted_at' => now(), 'payment_terms_accepted_at' => now(),
            'terms_accepted_at' => $this->terms_accepted_at ?? now(),
            'terms_version' => config('afrit6.terms_version'),
        ])->save();
        AuditLog::record('afrit6.terms_accepted', $this, ['version' => config('afrit6.terms_version')]);
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
