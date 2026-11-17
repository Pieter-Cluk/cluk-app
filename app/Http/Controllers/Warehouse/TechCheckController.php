<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Warehouse\Check;
use App\Models\Warehouse\ChargeNotice;
use Illuminate\View\View;

/** Tech Check: the pieces waiting for (or busy with) their check, and the return costs that follow. */
class TechCheckController extends Controller
{
    public function index(): View
    {
        $open = Check::with(['asset.item.checkProcedure.current', 'order.company', 'procedureVersion.procedure'])->whereNull('finished_at')->latest('id')->get()
            ->filter(fn ($c) => $c->asset);
        $done = Check::with(['asset.item', 'order', 'checkedBy'])->whereNotNull('finished_at')->latest('finished_at')->limit(15)->get();
        $notices = ChargeNotice::with('order.company.accountOwner')->whereIn('status', ['review', 'sent', 'objected'])->latest('id')->get();

        return view('warehouse.tech', ['open' => $open, 'done' => $done, 'notices' => $notices]);
    }
}
