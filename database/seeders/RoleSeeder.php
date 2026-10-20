<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * The starting roles, levels and rights (as agreed in the mockups).
 * Levels per module: 0 none, 1 view, 2 edit, 3 manage.
 * Can be changed later in Settings › Users › Roles & rights.
 */
class RoleSeeder extends Seeder
{
    /** Module order: companies, sales, purchasing, dryhire, warehouse, factory, afrit6, preinvoice, settings */
    public const ROLES = [
        'admin' => ['Admin', [3, 3, 3, 3, 3, 3, 3, 3, 3], ['*']],
        'manager' => ['Manager', [2, 3, 3, 3, 2, 3, 3, 2, 1], ['afrit6.approve_members', 'afrit6.door_codes', 'companies.see_health', 'finance.see_margins', 'orders.approve', 'sales.approve_low_margin', 'sales.edit_items', 'warehouse.release_repair', 'warehouse.sign_delivery', 'warehouse.write_off']],
        'sales' => ['Sales', [2, 2, 1, 2, 1, 2, 1, 1, 0], ['companies.see_health']],
        'asset-manager' => ['Asset manager', [1, 1, 2, 3, 2, 0, 0, 0, 0], ['finance.see_margins', 'orders.approve']],
        'warehouse' => ['Warehouse', [1, 0, 1, 2, 2, 1, 1, 0, 0], ['warehouse.sign_delivery']],
        'workspace-host' => ['Workspace host', [1, 0, 0, 0, 1, 1, 2, 0, 0], ['afrit6.approve_members', 'afrit6.door_codes']],
        'finance' => ['Finance', [2, 1, 2, 1, 1, 1, 1, 3, 0], ['companies.see_health', 'finance.see_margins', 'finance.send_to_okioki', 'finance.vat_treatment']],
        'read-only' => ['Read-only', [1, 1, 0, 1, 1, 1, 1, 0, 0], []],
    ];

    public function run(): void
    {
        $modules = config('cluk.modules');

        foreach (self::ROLES as $slug => [$name, $levels, $rights]) {
            $role = Role::updateOrCreate(['slug' => $slug], ['name' => $name]);
            $role->setLevels(array_combine($modules, $levels));
            $role->setRights($rights === ['*'] ? config('cluk.rights') : $rights);
        }
    }
}
