<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug'])]
class Role extends Model
{
    use HasFactory;

    public function moduleLevels(): HasMany
    {
        return $this->hasMany(RoleModuleLevel::class);
    }

    public function rights(): HasMany
    {
        return $this->hasMany(RoleRight::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function levelFor(string $module): int
    {
        return (int) ($this->moduleLevels->firstWhere('module', $module)?->level ?? 0);
    }

    public function hasRight(string $right): bool
    {
        return $this->rights->contains('right', $right);
    }

    /**
     * Set all module levels at once, e.g. ['companies' => 2, 'sales' => 1].
     *
     * @param  array<string, int>  $levels
     */
    public function setLevels(array $levels): void
    {
        foreach ($levels as $module => $level) {
            $this->moduleLevels()->updateOrCreate(['module' => $module], ['level' => $level]);
        }
        $this->unsetRelation('moduleLevels');
    }

    /**
     * @param  list<string>  $rights
     */
    public function setRights(array $rights): void
    {
        $this->rights()->whereNotIn('right', $rights)->delete();
        foreach ($rights as $right) {
            $this->rights()->firstOrCreate(['right' => $right]);
        }
        $this->unsetRelation('rights');
    }
}
