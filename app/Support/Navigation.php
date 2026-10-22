<?php

namespace App\Support;

use App\Models\User;

/**
 * Builds the sidebar for one person from config/navigation.php:
 * only items they may use, and only groups that keep at least one item.
 */
class Navigation
{
    /** @param array<string, mixed> $item */
    public static function canSee(array $item, User $user): bool
    {
        if (! $user->isActive()) {
            return false;
        }
        if (isset($item['right'])) {
            return $user->hasRight($item['right']);
        }
        if (isset($item['module'])) {
            [$module, $level] = $item['module'];

            return $user->canUseModule($module, $level);
        }

        return true;
    }

    /**
     * @return array{top: list<array>, groups: array<string, list<array>>, account: list<array>}
     */
    public static function for(User $user): array
    {
        $visible = fn (array $items) => array_values(array_filter($items, fn ($i) => self::canSee($i, $user)));

        $groups = [];
        foreach (config('navigation.groups') as $name => $items) {
            if ($shown = $visible($items)) {
                $groups[$name] = $shown;
            }
        }

        return [
            'top' => $visible(config('navigation.top')),
            'groups' => $groups,
            'account' => config('navigation.account'),
        ];
    }

    /** Can this person open the menu item with this key? */
    public static function allows(User $user, string $key): bool
    {
        $item = self::item($key);

        return $item !== null && self::canSee($item, $user);
    }

    /** @return array<string, mixed>|null */
    public static function item(string $key): ?array
    {
        $all = array_merge(config('navigation.top'), config('navigation.account'), ...array_values(config('navigation.groups')));

        foreach ($all as $item) {
            if ($item['key'] === $key) {
                return $item;
            }
        }

        return null;
    }
}
