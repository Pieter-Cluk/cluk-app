<?php

namespace App\Services\Warehouse;

use App\Models\AuditLog;
use App\Models\Equipment\Asset;
use App\Models\Equipment\Item;
use App\Models\Rental\Order;
use App\Models\User;
use App\Models\Warehouse\Charge;
use App\Models\Warehouse\Check;
use App\Support\Images;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Cleaning and the check on the test bench, and the charges they lead to. */
class Checks
{
    /** The function tests for this piece's category. */
    public static function tests(Asset $asset): array
    {
        $tests = config('warehouse.tests');

        return $tests[mb_strtolower((string) $asset->item?->category)] ?? $tests['default'];
    }

    /** @param  UploadedFile[]  $files */
    public static function addPhotos(Check $check, array $files, string $stage): void
    {
        $photos = $check->photos ?? [];
        foreach ($files as $f) {
            $photos[] = ['path' => Images::store($f, 'warehouse/checks/'.$check->id, 1600), 'stage' => $stage];
        }
        $check->update(['photos' => $photos]);
    }

    public static function photoCount(Check $check, ?string $stage = null): int
    {
        return collect($check->photos ?? [])->when($stage, fn ($c) => $c->where('stage', $stage))->count();
    }

    public static function clean(Asset $asset, int $dirt, User $by): Check
    {
        $check = Check::openFor($asset);
        if ($dirt >= (int) config('warehouse.photo_stage') && self::photoCount($check, 'clean') === 0) {
            throw ValidationException::withMessages(['photos' => __('warehouse.errors.photo_needed')]);
        }
        $check->update(['dirt' => $dirt, 'cleaned_at' => now(), 'cleaned_by_id' => $by->id]);

        return $check;
    }

    /**
     * Finish the check: missing parts are written off the stock, the charges wait for the
     * office, and the result says where the piece goes: Post-check or Repair.
     *
     * @param  array<string, string>  $tests  test => ok|fail
     * @param  array<int, int>  $missing  item id => qty
     * @param  list<array{type: string, note: ?string}>  $damages
     */
    public static function finish(Asset $asset, array $tests, array $missing, array $damages, bool $reset, User $by): Check
    {
        $check = Check::openFor($asset);
        $needed = self::tests($asset);
        if (array_diff($needed, array_keys(array_filter($tests, fn ($v) => in_array($v, ['ok', 'fail'], true))))) {
            throw ValidationException::withMessages(['tests' => __('warehouse.errors.tests')]);
        }
        $broken = in_array('fail', $tests, true) || $damages !== [];
        if (($broken || array_filter($missing)) && self::photoCount($check, 'check') === 0) {
            throw ValidationException::withMessages(['photos' => __('warehouse.errors.photo_needed')]);
        }
        if (! $broken && ! $reset) {
            throw ValidationException::withMessages(['reset' => __('warehouse.errors.reset')]);
        }

        return DB::transaction(function () use ($check, $asset, $tests, $missing, $damages, $reset, $by, $broken) {
            $order = $check->order;
            $missingList = [];
            foreach (array_filter($missing) as $itemId => $qty) {
                $item = Item::find($itemId);
                if (! $item) {
                    continue;
                }
                $missingList[] = ['item_id' => $item->id, 'qty' => (int) $qty];
                Moves::writeOff($item, (int) $qty, $order, $by, $asset->code);
                if ($order) {
                    $price = $item->catalog_price_cents ? $item->catalog_price_cents * (int) $qty : null;
                    self::charge($check, $order, 'missing', __('warehouse.charge.missing_on', ['name' => $item->name, 'number' => $item->number, 'code' => $asset->code], self::loc($order)), $price, $item, (int) $qty);
                }
            }
            if ($order && ($cost = (int) (config('warehouse.cleaning')[$check->dirt ?? 1] ?? 0)) > 0) {
                self::charge($check, $order, 'cleaning', __('warehouse.charge.cleaning', ['stage' => $check->dirt, 'code' => $asset->code], self::loc($order)), $cost);
            }
            foreach ($damages as $d) {
                if ($order) {
                    self::charge($check, $order, 'damage', __('warehouse.charge.damage', ['type' => __('warehouse.damage.'.$d['type'], [], self::loc($order)), 'code' => $asset->code], self::loc($order)).($d['note'] ? ' · '.$d['note'] : ''), null);
                }
            }
            $check->update(['tests' => $tests, 'missing' => $missingList, 'damages' => $damages, 'reset' => $reset, 'result' => $broken ? 'repair' : 'ok',
                'finished_at' => now(), 'checked_by_id' => $by->id]);
            AuditLog::record('warehouse.checked', $asset, ['code' => $asset->code, 'result' => $broken ? 'repair' : 'ok', 'order' => $order?->number], $by);

            return $check;
        });
    }

    private static function loc(Order $order): string
    {
        return array_key_exists($order->language, config('cluk.locales')) ? $order->language : 'en';
    }

    private static function charge(Check $check, Order $order, string $kind, string $label, ?int $cents, ?Item $item = null, int $qty = 1): Charge
    {
        return Charge::create(['order_id' => $order->id, 'asset_id' => $check->asset_id, 'check_id' => $check->id, 'item_id' => $item?->id, 'kind' => $kind,
            'label' => $label, 'qty' => $qty, 'amount_cents' => $cents, 'original_cents' => $cents]);
    }
}
