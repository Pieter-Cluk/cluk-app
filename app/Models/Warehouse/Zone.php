<?php

namespace App\Models\Warehouse;

use App\Models\Equipment\Item;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A zone of the warehouse on the route: Inbound, Cleaning, Pre-check, Test bench, … Racks. */
#[Fillable(['code', 'name', 'type', 'categories', 'next', 'note', 'sort', 'active'])]
class Zone extends Model
{
    protected $table = 'warehouse_zones';

    public const TYPES = ['inbound', 'cleaning', 'precheck', 'bench', 'postcheck', 'repair', 'storage', 'dispatch', 'afrit6'];

    /** "next" code that means: any storage position. */
    public const RACKS = 'RACKS';

    protected function casts(): array
    {
        return ['categories' => 'array', 'next' => 'array', 'active' => 'boolean'];
    }

    /** Standard zones are named in the reader's language until someone gives them a name. */
    public function label(): string
    {
        if ($this->name) {
            return $this->name;
        }
        $key = 'warehouse.zone_names.'.$this->code;

        return __($key) !== $key ? __($key) : $this->code;
    }

    /** Is this zone meant for this item? A zone for some categories only takes those, unless no zone of its type does. */
    public function takes(?Item $item): bool
    {
        if (empty($this->categories) || ! $item) {
            return true;
        }
        $cat = mb_strtolower((string) $item->category);
        if (in_array($cat, array_map('mb_strtolower', $this->categories), true)) {
            return true;
        }

        return ! static::where('type', $this->type)->where('active', true)->get()
            ->contains(fn (self $z) => in_array($cat, array_map('mb_strtolower', $z->categories ?? []), true));
    }

    public static function byCode(string $code): ?self
    {
        return static::where('code', strtoupper($code))->first();
    }
}
