<?php

namespace App\Models\Warehouse;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A pallet or shelf place: floor · row · rack · layer · spot, e.g. GA1A1. */
#[Fillable(['code', 'floor', 'row', 'rack', 'layer', 'spot', 'kind', 'use', 'active'])]
class Position extends Model
{
    protected $table = 'warehouse_positions';

    public const USES = ['rental', 'afrit6', 'sales', 'spare'];

    protected function casts(): array
    {
        return ['rack' => 'integer', 'spot' => 'integer', 'active' => 'boolean'];
    }

    public static function byCode(string $code): ?self
    {
        return static::where('code', strtoupper($code))->first();
    }

    /** Ground floor layer A is the floor: always pallet places. */
    public function isGroundLayer(): bool
    {
        return $this->floor === 'G' && $this->layer === 'A';
    }

    public function layerCode(): string
    {
        return $this->floor.$this->row.$this->rack.$this->layer;
    }
}
