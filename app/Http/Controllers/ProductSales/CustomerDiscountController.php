<?php

namespace App\Http\Controllers\ProductSales;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Product\Brand;
use App\Models\ProductSales\CustomerDiscount;
use App\Services\ProductSales\CustomerPrices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Customer card › Sales discounts per brand. Setting them needs the right
 * "Sales: approve low margin", because they change the prices the customer
 * sees on the website and gets on new quotes. Every change is in the audit log.
 */
class CustomerDiscountController extends Controller
{
    public function store(Request $request, Company $company): RedirectResponse
    {
        $d = $request->validate([
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'discount' => ['required', 'numeric', 'min:0', 'max:90'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $brandId = $d['brand_id'] ?? null;
        $bp = (int) round($d['discount'] * 100);
        $row = CustomerDiscount::where('company_id', $company->id)->where('brand_id', $brandId)->first();
        $old = $row?->discount_bp;
        $row ??= new CustomerDiscount(['company_id' => $company->id, 'brand_id' => $brandId]);
        $row->fill(['discount_bp' => $bp, 'note' => $d['note'] ?? null, 'set_by_id' => $request->user()->id])->save();
        CustomerPrices::flush();
        AuditLog::record('sales.customer_discount', $company, [
            'brand' => $brandId ? Brand::find($brandId)?->name : '*', 'discount' => ($old !== null ? ($old / 100).'% → ' : '').($bp / 100).'%',
        ], $request->user());

        return back()->with('status', __('psales.discounts.saved'));
    }

    public function destroy(Request $request, Company $company, CustomerDiscount $discount): RedirectResponse
    {
        abort_unless($discount->company_id === $company->id, 404);
        AuditLog::record('sales.customer_discount_removed', $company, ['brand' => $discount->brand?->name ?? '*', 'discount' => ($discount->discount_bp / 100).'%'], $request->user());
        $discount->delete();
        CustomerPrices::flush();

        return back()->with('status', __('psales.discounts.removed'));
    }
}
