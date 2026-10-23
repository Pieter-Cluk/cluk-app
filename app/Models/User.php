<?php

namespace App\Models;

use App\Support\Navigation;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A CLUK staff member.
 *
 * Access = the role's level per module, unless the person has an override
 * for that module, plus the role's rights and the person's extra rights.
 * A disabled person has no access at all.
 */
#[Fillable([
    'first_name', 'last_name', 'email', 'password', 'microsoft_id', 'title', 'department',
    'phone', 'mobile', 'location', 'locale', 'status', 'is_commercial', 'email_login', 'code_email', 'role_id', 'reports_to_id',
    'theme', 'density', 'start_page', 'notify', 'daily_at', 'daily_sent_on', 'away_from', 'away_until', 'away_delegate_id',
])]
#[Hidden(['password', 'remember_token', 'door_code'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'door_code' => 'encrypted',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'is_commercial' => 'boolean',
            'email_login' => 'boolean',
            'notify' => 'array',
            'daily_sent_on' => 'date',
            'away_from' => 'date',
            'away_until' => 'date',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    protected function initials(): Attribute
    {
        return Attribute::get(fn () => mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1)));
    }

    /** Emails about what happens to your work; each can be switched off in My preferences. */
    public const MAIL_EVENTS = ['quote_answered', 'order_handed', 'pf_back', 'credit_alert'];

    /** Where "my start page" can point (menu key => route name). */
    public const START_PAGES = ['dashboard' => 'dashboard', 'management' => 'management', 'sales' => 'deals.index', 'companies' => 'companies.index', 'afrit6' => 'afrit6.staff.index', 'warehouse' => 'warehouse.index', 'dryhire' => 'equipment.index'];

    public function wantsMail(string $event): bool
    {
        return (bool) ($this->notify[$event] ?? true);
    }

    /** Away today, with someone who takes over the emails meant for this person. */
    public function awayNow(): bool
    {
        return $this->away_delegate_id && $this->away_from && $this->away_until && today()->between($this->away_from, $this->away_until);
    }

    public function awayDelegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'away_delegate_id');
    }

    /** The page that opens after signing in (falls back to the dashboard when the module is not open to them). */
    public function homeUrl(): string
    {
        $key = $this->start_page;
        if ($key && isset(self::START_PAGES[$key]) && Navigation::allows($this, $key)) {
            return route(self::START_PAGES[$key]);
        }

        return route('dashboard');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reports_to_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(User::class, 'reports_to_id');
    }

    public function moduleOverrides(): HasMany
    {
        return $this->hasMany(UserModuleOverride::class);
    }

    public function extraRights(): HasMany
    {
        return $this->hasMany(UserRight::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    // ---------------------------------------------------------------- access

    /** Level 0–3 for a module: the person's override wins over the role. */
    public function moduleLevel(string $module): int
    {
        if (! $this->isActive()) {
            return 0;
        }

        $override = $this->moduleOverrides->firstWhere('module', $module);
        if ($override !== null) {
            return (int) $override->level;
        }

        return $this->role?->levelFor($module) ?? 0;
    }

    public function hasOverride(string $module): bool
    {
        return $this->moduleOverrides->contains('module', $module);
    }

    public function canUseModule(string $module, int $atLeast = 1): bool
    {
        return $this->moduleLevel($module) >= $atLeast;
    }

    /**
     * Modules for the menu, in the configured order.
     *
     * @return list<string>
     */
    public function allowedModules(): array
    {
        return array_values(array_filter(config('cluk.modules'), fn (string $m) => $this->canUseModule($m)));
    }

    public function hasRight(string $right): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        return $this->extraRights->contains('right', $right) || (bool) $this->role?->hasRight($right);
    }

    public function setModuleOverride(string $module, ?int $level, ?User $by = null): void
    {
        $before = $this->hasOverride($module) ? $this->moduleLevel($module) : null;

        if ($level === null) {
            $this->moduleOverrides()->where('module', $module)->delete();
        } else {
            $this->moduleOverrides()->updateOrCreate(['module' => $module], ['level' => $level]);
        }
        $this->unsetRelation('moduleOverrides');

        AuditLog::record('user.module_override', $this, ['module' => $module, 'from' => $before, 'to' => $level], $by);
    }

    public function grantRight(string $right, ?User $by = null): void
    {
        $this->extraRights()->firstOrCreate(['right' => $right]);
        $this->unsetRelation('extraRights');
        AuditLog::record('user.right_granted', $this, ['right' => $right], $by);
    }

    public function revokeRight(string $right, ?User $by = null): void
    {
        $this->extraRights()->where('right', $right)->delete();
        $this->unsetRelation('extraRights');
        AuditLog::record('user.right_revoked', $this, ['right' => $right], $by);
    }

    // ------------------------------------------------------- reporting line

    /**
     * Everyone above this person in the reporting line, nearest first.
     * Stops on a loop so a wrong "reports to" can never hang the app.
     *
     * @return list<int>
     */
    public function managerChainIds(): array
    {
        $ids = [];
        $next = $this->reports_to_id;

        while ($next !== null && ! in_array($next, $ids, true) && $next !== $this->id) {
            $ids[] = $next;
            $next = static::query()->whereKey($next)->value('reports_to_id');
        }

        return $ids;
    }

    /** True when this person reports to $manager, directly or further up. */
    public function reportsToUser(User $manager): bool
    {
        return in_array($manager->id, $this->managerChainIds(), true);
    }

    // --------------------------------------------------------- reassignment

    /**
     * May this person reassign a task that is now with $assignee?
     * Yes for the assignee, and for everyone above the assignee.
     */
    public function canReassignFrom(User $assignee): bool
    {
        return $this->isActive() && ($this->is($assignee) || $assignee->reportsToUser($this));
    }

    /**
     * May this person hand a task to $target?
     * Allowed: themselves, someone with the same role and job title,
     * or someone who reports to them (directly or further down).
     */
    public function canHandTaskTo(User $target): bool
    {
        if (! $this->isActive() || ! $target->isActive()) {
            return false;
        }

        if ($this->is($target)) {
            return true;
        }

        $sameRoleAndTitle = $this->role_id !== null
            && $this->role_id === $target->role_id
            && $this->title !== null
            && mb_strtolower($this->title) === mb_strtolower((string) $target->title);

        return $sameRoleAndTitle || $target->reportsToUser($this);
    }
}
