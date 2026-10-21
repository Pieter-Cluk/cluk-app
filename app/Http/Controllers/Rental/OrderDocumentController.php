<?php

namespace App\Http\Controllers\Rental;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanyDetail;
use App\Models\Rental\Order;
use App\Services\Rental\OrderMails;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The documents of an approved rental order, numbered like the order:
 * R-PICK (pick list, internal), R-DN (delivery note), R-RET (return
 * checklist, internal) and R-PF (proforma, payment request).
 * Customer documents are in the customer's language, internal ones in yours.
 */
class OrderDocumentController extends Controller
{
    public const TYPES = ['pick' => 'R-PICK', 'dn' => 'R-DN', 'ret' => 'R-RET', 'pf' => 'R-PF'];

    public const FOR_CUSTOMER = ['dn', 'pf'];

    public static function number(Order $order, string $type): string
    {
        return str_replace('R-OC', self::TYPES[$type], $order->number);
    }

    public function show(Order $order, string $type): View
    {
        abort_unless(isset(self::TYPES[$type]) && $order->status === 'approved', 404);

        return self::render($order, $type);
    }

    public static function render(Order $order, string $type): View
    {
        $order->load(['lines.item.kit', 'lines.item.contents', 'lines.assets.asset', 'lines.subhireLines.request.supplier', 'company', 'contact', 'handedBy', 'approvedBy']);
        if (in_array($type, self::FOR_CUSTOMER, true)) {
            app()->setLocale($order->language);
        }

        return view('rental.docs.'.$type, [
            'order' => $order, 'type' => $type, 'code' => self::number($order, $type), 'me' => CompanyDetail::current(),
        ]);
    }

    /** Email the proforma to the customer (payment before collection). */
    public function sendProforma(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->status === 'approved' && $order->customer_email && $request->user()->hasRight('orders.approve'), 403);
        $ok = OrderMails::proforma($order, $request->user());
        AuditLog::record('order.proforma_sent', $order, ['to' => $order->customer_email, 'number' => self::number($order, 'pf')]);

        return back()->with('status', $ok ? __('docs.flash.pf_sent', ['email' => $order->customer_email]) : __('docs.flash.pf_not_sent'));
    }
}
