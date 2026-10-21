<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DocumentSetting;
use App\Models\Factory\Booking;
use App\Models\Rental\Order;
use App\Models\Sales\Quote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/** Settings › Documents: every document family, its numbering and rules, the commercial notes and the seasonal message. */
class DocumentController extends Controller
{
    public static function all(): array
    {
        $out = [];
        foreach (config('documents.families') as $family => $docs) {
            foreach ($docs as $type => $d) {
                $out[$type] = $d + ['family' => $family, 'type' => $type];
            }
        }

        return $out;
    }

    /** A real example of the document, when one exists. */
    public static function example(string $type): ?string
    {
        $order = fn () => Order::where('status', 'approved')->latest('id')->first();

        return match ($type) {
            'R-QUO' => ($q = Quote::where('number', 'like', 'R-QUO-%')->latest('id')->first()) ? route('quotes.print', $q) : null,
            'R-OC' => ($o = $order()) ? route('orders.print', $o) : null,
            'R-PICK', 'R-DN', 'R-RET', 'R-PF' => ($o = $order()) ? route('orders.doc', [$o, strtolower(substr($type, 2))]) : null,
            'F-BC' => ($b = Booking::whereIn('status', ['option', 'confirmed', 'done'])->latest('id')->first()) ? route('factory.print', $b) : null,
            default => Route::has('docs.example') ? route('docs.example', $type) : null,
        };
    }

    public function index(Request $request): View
    {
        $all = self::all();
        $family = array_key_exists($request->query('family'), config('documents.families')) ? $request->query('family') : 'all';
        $type = array_key_exists((string) $request->query('doc'), $all) ? $request->query('doc') : array_key_first($all);

        return view('settings.documents', [
            'all' => $all, 'family' => $family, 'type' => $type, 'doc' => $all[$type],
            'note' => DocumentSetting::get('note.'.$type), 'season' => DocumentSetting::get('season'),
            'example' => self::example($type),
        ]);
    }

    public function updateNote(Request $request, string $type): RedirectResponse
    {
        abort_unless((self::all()[$type]['customer'] ?? false), 404);
        $d = $request->validate(['text' => ['array'], 'text.*' => ['nullable', 'string', 'max:600'], 'on' => ['boolean']]);
        DocumentSetting::put('note.'.$type, ['on' => $request->boolean('on'), 'text' => array_intersect_key($d['text'] ?? [], config('cluk.locales'))], $request->user());
        AuditLog::record('documents.note', null, ['type' => $type, 'on' => $request->boolean('on')], $request->user());

        return back()->with('status', __('documents.saved'));
    }

    public function updateSeason(Request $request): RedirectResponse
    {
        $d = $request->validate(['text' => ['array'], 'text.*' => ['nullable', 'string', 'max:300'], 'on' => ['boolean'],
            'from' => ['nullable', 'date', 'required_if:on,1'], 'to' => ['nullable', 'date', 'after_or_equal:from', 'required_if:on,1']]);
        DocumentSetting::put('season', ['on' => $request->boolean('on'), 'from' => $d['from'] ?? null, 'to' => $d['to'] ?? null,
            'text' => array_intersect_key($d['text'] ?? [], config('cluk.locales'))], $request->user());
        AuditLog::record('documents.season', null, ['from' => $d['from'] ?? null, 'to' => $d['to'] ?? null, 'on' => $request->boolean('on')], $request->user());

        return back()->with('status', __('documents.saved'));
    }
}
