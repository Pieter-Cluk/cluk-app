<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\AccessGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Settings › Users: the CLUK team. People sign in with their Microsoft 365
 * account; this screen decides who gets in and what they may do.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', User::STATUS_ACTIVE);
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->with(['role', 'reportsTo'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($request->filled('role'), fn ($query) => $query->where('role_id', $request->integer('role')))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtolower($q)).'%';
                $query->where(fn ($w) => $w
                    ->whereRaw('lower(first_name) like ?', [$like])
                    ->orWhereRaw('lower(last_name) like ?', [$like])
                    ->orWhereRaw('lower(email) like ?', [$like])
                    ->orWhereRaw('lower(coalesce(title, \'\')) like ?', [$like]));
            })
            ->orderBy('first_name')->orderBy('last_name')
            ->paginate(50)->withQueryString();

        return view('settings.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
            'status' => $status,
            'q' => $q,
            'counts' => [
                'active' => User::where('status', User::STATUS_ACTIVE)->count(),
                'disabled' => User::where('status', User::STATUS_DISABLED)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('settings.users.create', [
            'user' => new User(['locale' => 'nl', 'status' => User::STATUS_ACTIVE]),
            'roles' => Role::orderBy('name')->get(),
            'managers' => $this->possibleManagers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $user = AccessGuard::change(function () use ($data) {
            $user = User::create($data + ['status' => User::STATUS_ACTIVE]);
            AuditLog::record('user.created', $user, ['email' => $user->email, 'role' => $user->role?->name]);

            return $user;
        });

        return redirect()->route('settings.users.edit', $user)->with('status', __('users.flash.created', ['name' => $user->name]));
    }

    public function edit(User $user): View
    {
        $user->load(['role.moduleLevels', 'role.rights', 'moduleOverrides', 'extraRights', 'reportsTo', 'directReports']);

        return view('settings.users.edit', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
            'managers' => $this->possibleManagers($user),
            'activity' => AuditLog::query()
                ->with('actor')
                ->where(fn ($w) => $w
                    ->where(fn ($s) => $s->where('subject_type', $user->getMorphClass())->where('subject_id', $user->id))
                    ->orWhere('actor_id', $user->id))
                ->latest('id')->limit(15)->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        $oldRole = $user->role?->name;

        AccessGuard::change(function () use ($user, $data, $oldRole) {
            $user->fill($data);
            $changes = array_intersect_key($user->getDirty(), array_flip(array_keys($data)));
            $user->save();

            if (array_key_exists('role_id', $changes)) {
                AuditLog::record('user.role_changed', $user, ['from' => $oldRole, 'to' => $user->fresh()->role?->name]);
                unset($changes['role_id']);
            }
            if ($changes !== []) {
                AuditLog::record('user.updated', $user, ['changes' => $changes]);
            }
        });

        return redirect()->route('settings.users.edit', $user)->with('status', __('users.flash.saved'));
    }

    /** Per-module levels that differ from the role, and extra rights. */
    public function updateAccess(Request $request, User $user): RedirectResponse
    {
        $modules = config('cluk.modules');
        $request->validate([
            'overrides' => ['array'],
            'overrides.*' => ['nullable', 'in:,0,1,2,3'],
            'rights' => ['array'],
            'rights.*' => [Rule::in(config('cluk.rights'))],
        ]);

        AccessGuard::change(function () use ($request, $user, $modules) {
            $user->load(['role.rights', 'role.moduleLevels', 'moduleOverrides', 'extraRights']);

            foreach ($modules as $module) {
                $raw = $request->input("overrides.$module");
                $wanted = ($raw === null || $raw === '') ? null : (int) $raw;
                $current = $user->moduleOverrides->firstWhere('module', $module)?->level;
                $current = $current === null ? null : (int) $current;

                if ($wanted !== $current) {
                    $user->setModuleOverride($module, $wanted);
                }
            }

            // Only rights the role does not already give can be added per person.
            $wanted = collect($request->input('rights', []))->reject(fn ($r) => $user->role?->hasRight($r))->values();
            $current = $user->extraRights->pluck('right');

            foreach ($wanted->diff($current) as $right) {
                $user->grantRight($right);
            }
            foreach ($current->diff($wanted) as $right) {
                if (! $user->role?->hasRight($right)) {
                    $user->revokeRight($right);
                }
            }
        });

        return redirect()->route('settings.users.edit', $user)->with('status', __('users.flash.access_saved'));
    }

    public function disable(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['access' => __('users.errors.not_yourself')]);
        }

        AccessGuard::change(function () use ($user) {
            $user->forceFill(['status' => User::STATUS_DISABLED, 'remember_token' => null])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            AuditLog::record('user.disabled', $user);
        });

        return redirect()->route('settings.users.edit', $user)->with('status', __('users.flash.disabled', ['name' => $user->name]));
    }

    public function enable(User $user): RedirectResponse
    {
        $user->forceFill(['status' => User::STATUS_ACTIVE])->save();
        AuditLog::record('user.enabled', $user);

        return redirect()->route('settings.users.edit', $user)->with('status', __('users.flash.enabled', ['name' => $user->name]));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $user = null): array
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'title' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'location' => ['nullable', 'string', 'max:120'],
            'locale' => ['required', Rule::in(array_keys(config('cluk.locales')))],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'reports_to_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $data['is_commercial'] = $request->boolean('is_commercial');
        $data['email_login'] = $request->boolean('email_login');
        if ($data['email_login'] && ! str_ends_with($data['email'], '@'.config('cluk.staff_email_domain'))) {
            throw ValidationException::withMessages(['email_login' => __('login.code.wrong_domain', ['domain' => config('cluk.staff_email_domain')])]);
        }
        $data['role_id'] = (int) $data['role_id'];
        $data['reports_to_id'] = isset($data['reports_to_id']) ? (int) $data['reports_to_id'] : null;

        // "Reports to" may not be the person, nor anyone who reports to them (a loop).
        if ($user !== null && $data['reports_to_id'] !== null) {
            $manager = User::find($data['reports_to_id']);
            if ($manager->is($user) || $manager->reportsToUser($user)) {
                throw ValidationException::withMessages(['reports_to_id' => __('users.errors.reporting_loop')]);
            }
        }

        return $data;
    }

    /** Active colleagues who can be someone's manager (not the person, nor their own reports). */
    private function possibleManagers(?User $user = null)
    {
        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('first_name')->orderBy('last_name')
            ->get()
            ->reject(fn (User $m) => $user !== null && ($m->is($user) || $m->reportsToUser($user)))
            ->values();
    }
}
