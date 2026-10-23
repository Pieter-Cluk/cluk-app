<?php

namespace App\Http\Controllers\ProductSales;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Product\Brand;
use App\Models\Product\Category;
use App\Models\ProductSales\CustomerDiscount;
use App\Models\ProductSales\SalesItem;
use App\Services\Afrit6\Pricing;
use App\Services\ProductSales\CustomerPrices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Customer card › Standard sales conditions: a default %, a different % per
 * brand or sales category, and fixed net prices per product. Setting them
 * needs the right "Sales: approve low margin", because they set the prices
 * the customer sees on the website and gets on new quotes. Every change is in
 * the audit log.
 */
class CustomerDiscountController extends Controller
{
    /** A percentage: target "default", "b:{brand id}" or "c:{category id}". */
    public function store(Request $request, Company $company): RedirectResponse
    {
        $d = $request->validate([
            'target' => ['required', 'string', 'regex:/^(default|b:\d+|c:\d+)$/'],
            'discount' => ['required', 'numeric', 'min:0', 'max:90'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        [$kind, $id] = array_pad(explode(':', $d['target']), 2, null);
        $brand = $kind === 'b' ? Brand::find($id) : null;
        $cat = $kind === 'c' ? Category::of(SalesItem::CATALOGUE)->find($id) : null;
        if (($kind === 'b' && ! $brand) || ($kind === 'c' && ! $cat)) {
            throw ValidationException::withMessages(['target' => __('psales.discounts.unknown_target')]);
        }
        $row = CustomerDiscount::where('company_id', $company->id)->whereNull('sales_item_id')
            ->where('brand_id', $brand?->id)->where('category_id', $cat?->id)->first();
        $this->save($request, $company, $row, ['brand_id' => $brand?->id, 'category_id' => $cat?->id, 'sales_item_id' => null,
            'discount_bp' => (int) round($d['discount'] * 100), 'net_cents' => null, 'note' => $d['note'] ?? null],
            $brand?->name ?? ($cat ? $cat->label('en') : __('psales.discounts.default', [], 'en')), ($d['discount'] + 0).'%');

        return back()->with('status', __('psales.discounts.saved'));
    }

    /** A fixed net price for one product (overrides every %). */
    public function net(Request $request, Company $company): RedirectResponse
    {
        $d = $request->validate([
            'item' => ['required', 'string', 'max:200'],
            'net_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $number = strtoupper(trim(explode('·', $d['item'])[0]));
        $item = SalesItem::where('number', $number)->first() ?? (ctype_digit($d['item']) ? SalesItem::find((int) $d['item']) : null);
        if (! $item) {
            throw ValidationException::withMessages(['item' => __('psales.discounts.unknown_item', ['value' => $d['item']])]);
        }
        $row = CustomerDiscount::where('company_id', $company->id)->where('sales_item_id', $item->id)->first();
        $cents = (int) round($d['net_price'] * 100);
        $this->save($request, $company, $row, ['brand_id' => null, 'category_id' => null, 'sales_item_id' => $item->id, 'discount_bp' => null, 'net_cents' => $cents, 'note' => $d['note'] ?? null],
            $item->number.' '.$item->name(), Pricing::eur($cents).' net');

        return back()->with('status', __('psales.discounts.saved'));
    }

    private function save(Request $request, Company $company, ?CustomerDiscount $row, array $values, string $what, string $value): void
    {
        $old = $row ? ($row->net_cents !== null ? Pricing::eur($row->net_cents).' net' : ($row->discount_bp / 100).'%') : null;
        $row ??= new CustomerDiscount(['company_id' => $company->id]);
        $row->fill($values + ['set_by_id' => $request->user()->id])->save();
        CustomerPrices::flush();
        AuditLog::record('sales.customer_discount', $company, ['for' => $what, 'value' => ($old !== null ? $old.' → ' : '').$value], $request->user());
    }

    public function destroy(Request $request, Company $company, CustomerDiscount $discount): RedirectResponse
    {
        abort_unless($discount->company_id === $company->id, 404);
        AuditLog::record('sales.customer_discount_removed', $company, [
            'for' => $discount->item?->number ?? $discount->brand?->name ?? $discount->category?->label('en') ?? 'default',
            'value' => $discount->net_cents !== null ? Pricing::eur($discount->net_cents).' net' : ($discount->discount_bp / 100).'%',
        ], $request->user());
        $discount->delete();
        CustomerPrices::flush();

        return back()->with('status', __('psales.discounts.removed'));
    }
}
