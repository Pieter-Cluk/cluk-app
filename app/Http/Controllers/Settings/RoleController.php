<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Support\AccessGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Settings › Users › Roles & rights: one matrix with every role's level per
 * module and its extra rights. Every change is written to the audit log.
 */
class RoleController extends Controller
{
    public function index(): View
    {
        return view('settings.roles.index', [
            'roles' => Role::with(['moduleLevels', 'rights'])->withCount('users')->orderBy('id')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'shown' => ['required', 'array'],
            'shown.*' => ['integer'],
            'levels' => ['required', 'array'],
            'levels.*' => ['array'],
            'levels.*.*' => ['required', 'integer', 'between:0,3'],
            'rights' => ['array'],
            'rights.*' => ['array'],
            'rights.*.*' => [Rule::in(config('cluk.rights'))],
        ]);

        $changed = AccessGuard::change(function () use ($request) {
            $changed = 0;

            // Only roles that were on the screen (a role added meanwhile stays untouched).
            $shown = array_map('intval', $request->input('shown', []));
            foreach (Role::with(['moduleLevels', 'rights'])->whereKey($shown)->get() as $role) {
                $levels = [];
                $levelChanges = [];
                foreach (config('cluk.modules') as $module) {
                    $from = $role->levelFor($module);
                    $to = (int) $request->input("levels.{$role->id}.{$module}", $from);
                    $levels[$module] = $to;
                    if ($from !== $to) {
                        $levelChanges[$module] = [$from, $to];
                    }
                }

                $wanted = array_values(array_intersect(config('cluk.rights'), $request->input("rights.{$role->id}", [])));
                $current = $role->rights->pluck('right')->all();
                $added = array_values(array_diff($wanted, $current));
                $removed = array_values(array_diff($current, $wanted));

                if ($levelChanges === [] && $added === [] && $removed === []) {
                    continue;
                }

                $role->setLevels($levels);
                $role->setRights($wanted);
                AuditLog::record('role.updated', $role, array_filter([
                    'levels' => $levelChanges, 'rights_added' => $added, 'rights_removed' => $removed,
                ]));
                $changed++;
            }

            return $changed;
        });

        return redirect()->route('settings.roles.index')
            ->with('status', $changed ? __('users.flash.roles_saved', ['count' => $changed]) : __('users.flash.nothing_changed'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'name')],
            'copy_from' => ['nullable', 'integer', 'exists:roles,id'],
        ]);

        $slug = $base = Str::slug($data['name']) ?: 'role';
        for ($i = 2; Role::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        $role = Role::create(['name' => $data['name'], 'slug' => $slug]);
        $source = isset($data['copy_from']) ? Role::with(['moduleLevels', 'rights'])->find($data['copy_from']) : null;

        $role->setLevels(collect(config('cluk.modules'))->mapWithKeys(fn ($m) => [$m => $source?->levelFor($m) ?? 0])->all());
        $role->setRights($source?->rights->pluck('right')->all() ?? []);
        AuditLog::record('role.created', $role, array_filter(['copied_from' => $source?->name]));

        return redirect()->route('settings.roles.index')->with('status', __('users.flash.role_created', ['name' => $role->name]));
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['access' => __('users.errors.role_in_use', ['name' => $role->name])]);
        }

        AuditLog::record('role.deleted', null, ['name' => $role->name]);
        $role->delete();

        return redirect()->route('settings.roles.index')->with('status', __('users.flash.role_deleted', ['name' => $role->name]));
    }
}
