<?php

namespace App\Models\Warehouse;

use App\Models\Equipment\Asset;
use App\Models\Rental\Order;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A light dolly (meat rack) or storage box: pieces go in it, it moves as one and goes with an order at no charge. */
#[Fillable(['code', 'kind', 'name', 'location', 'out_order_id', 'active'])]
class Carrier extends Model
{
    protected $table = 'warehouse_carriers';

    public const KINDS = ['dolly', 'box'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'carrier_id')->orderBy('code');
    }

    public function outOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'out_order_id');
    }

    public static function byCode(?string $code): ?self
    {
        return $code ? self::where('code', strtoupper(trim($code)))->where('active', true)->first() : null;
    }

    /** "Light dolly DOLLY-03" / "Storage box BOX-12", plus its own name when it has one. */
    public function label(): string
    {
        return __('warehouse.carriers.kinds.'.$this->kind).' '.$this->code.($this->name ? ' · '.$this->name : '');
    }
}
