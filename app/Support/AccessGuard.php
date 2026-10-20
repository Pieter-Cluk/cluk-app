<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Runs a change to roles, rights or people and undoes it when it would
 * leave nobody who can manage users (so nobody can lock CLUK out of its
 * own app by mistake).
 */
class AccessGuard
{
    /**
     * @template T
     *
     * @param  callable(): T  $change
     * @return T
     */
    public static function change(callable $change): mixed
    {
        return DB::transaction(function () use ($change) {
            $result = $change();

            if (self::userManagerCount() === 0) {
                throw ValidationException::withMessages(['access' => __('users.errors.last_manager')]);
            }

            return $result;
        });
    }

    public static function userManagerCount(): int
    {
        return User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->with(['role.rights', 'extraRights'])
            ->get()
            ->filter(fn (User $u) => $u->hasRight('users.manage'))
            ->count();
    }
}
