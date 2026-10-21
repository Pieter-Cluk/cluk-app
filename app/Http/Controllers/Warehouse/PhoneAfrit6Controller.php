<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Afrit6\ServiceRequest;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\Rental\Order;
use App\Models\Warehouse\Delivery;
use App\Models\Warehouse\Position;
use App\Services\Warehouse\Deliveries;
use App\Services\Warehouse\Moves;
use App\Support\Images;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** On the phone: Afrit 6 warehouse tasks (photo + scans as proof) and unannounced deliveries. */
class PhoneAfrit6Controller extends Controller
{
    public const KINDS = ['receive', 'send', 'packing', 'shelf', 'pallet'];

    /** Places Afrit 6 goods may go: positions rented to Afrit 6, its zones, Dispatch and the unknown goods shelf. */
    public static function placeOk(string $code): ?string
    {
        $found = Moves::resolve($code);
        if (! $found || $found['type'] === 'asset') {
            return null;
        }
        $m = $found['model'];
        if ($m instanceof Position) {
            return $m->use === 'afrit6' ? $m->code : null;
        }

        return in_array($m->type, ['afrit6', 'dispatch', 'inbound'], true) ? $m->code : null;
    }

    public function index(): View
    {
        return view('warehouse.phone.a6', [
            'tasks' => ServiceRequest::with(['member.company', 'assignee'])->whereIn('kind', self::KINDS)
                ->whereIn('status', [ServiceRequest::PENDING, ServiceRequest::CONFIRMED, ServiceRequest::WAITING])
                ->whereDate('date', '<=', today()->addDays(2))->orderBy('date')->get(),
        ]);
    }

    public function show(ServiceRequest $task): View
    {
        abort_unless(in_array($task->kind, self::KINDS, true), 404);

        return view('warehouse.phone.a6task', ['task' => $task->load(['member.company', 'assignee'])]);
    }

    private function mine(Request $request, ServiceRequest $task): void
    {
        abort_unless(in_array($task->kind, self::KINDS, true), 404);
        $me = $request->user();
        if (! $task->assignee_id) {
            $task->update(['assignee_id' => $me->id]);
            AuditLog::record('afrit6.task_assigned', $task, ['to' => $me->name], $me);
        }
        abort_unless($task->assignee_id === $me->id, 403);
    }

    public function photo(Request $request, ServiceRequest $task): RedirectResponse
    {
        $this->mine($request, $task);
        $request->validate(['photos' => ['required', 'array', 'max:6'], 'photos.*' => Images::rule()]);
        $photos = $task->photos ?? [];
        foreach ($request->file('photos', []) as $f) {
            $photos[] = Images::store($f, 'warehouse/afrit6/'.$task->id, 1600);
        }
        $task->update(['photos' => $photos]);

        return back();
    }

    public function scan(Request $request, ServiceRequest $task): RedirectResponse
    {
        $this->mine($request, $task);
        $code = self::placeOk((string) $request->validate(['code' => ['required', 'string', 'max:200']])['code']);
        if (! $code) {
            throw ValidationException::withMessages(['code' => __('warehouse.a6.wrong_place')]);
        }
        $scans = $task->scans ?? [];
        $scans[] = ['code' => $code, 'at' => now()->toIso8601String(), 'by' => $request->user()->id];
        $task->update(['scans' => $scans]);

        return back()->with('status', __('warehouse.a6.scanned', ['place' => Moves::label($code)]));
    }

    public function done(Request $request, ServiceRequest $task): RedirectResponse
    {
        $this->mine($request, $task);
        $d = $request->validate(['colis' => ['nullable', 'integer', 'min:0', 'max:9999']]);
        if ($task->status !== ServiceRequest::CONFIRMED) {
            throw ValidationException::withMessages(['task' => __('workspace.tasks.confirm_first')]);
        }
        if (empty($task->photos)) {
            throw ValidationException::withMessages(['photos' => __('warehouse.errors.photo_needed')]);
        }
        if (empty($task->scans)) {
            throw ValidationException::withMessages(['code' => __('warehouse.a6.scan_first')]);
        }
        $data = ['status' => ServiceRequest::DONE, 'done_at' => now()];
        if ($task->kind === 'packing' && isset($d['colis'])) {
            $data['colis'] = (int) $d['colis'];
        }
        $task->update($data);
        AuditLog::record('afrit6.task_done', $task, ['places' => collect($task->scans)->pluck('code')->unique()->implode(', ')], $request->user());

        return redirect()->route('w.a6')->with('status', __('workspace.tasks.marked_done'));
    }

    public function media(string $type, int $id, int $i)
    {
        $path = match ($type) {
            'a6' => ServiceRequest::findOrFail($id)->photos[$i] ?? null,
            default => Delivery::findOrFail($id)->photos[$i] ?? null,
        };
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, max-age=86400']);
    }

    // ------------------------------------------------------------------ unannounced deliveries

    public function delivery(): View
    {
        return view('warehouse.phone.delivery', [
            'members' => Member::with('company')->where('status', 'active')->get()->sortBy(fn ($m) => mb_strtolower($m->companyName()))->values(),
            'orders' => Order::with('company')->where('status', 'approved')->whereNotNull('loaded_at')->whereNull('returned_at')->orderBy('out_until')->get(),
        ]);
    }

    public function deliveryStore(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:6'], 'photos.*' => Images::rule(),
            'colli' => ['required', 'integer', 'min:0', 'max:999'], 'pallets' => ['required', 'integer', 'min:0', 'max:99'],
            'damaged' => ['boolean'], 'carrier' => ['nullable', 'string', 'max:80'], 'label_text' => ['nullable', 'string', 'max:300'],
            'match' => ['required', Rule::in(['member', 'return', 'unknown'])],
            'member_id' => ['required_if:match,member', 'nullable', 'integer', 'exists:members,id'],
            'order_id' => ['required_if:match,return', 'nullable', 'integer', 'exists:orders,id'],
        ]);
        if ((int) $d['colli'] + (int) $d['pallets'] === 0) {
            throw ValidationException::withMessages(['colli' => __('warehouse.delivery.count_first')]);
        }
        $me = $request->user();
        $delivery = Delivery::create(['user_id' => $me->id, 'colli' => $d['colli'], 'pallets' => $d['pallets'], 'damaged' => $request->boolean('damaged'),
            'carrier' => $d['carrier'] ?? null, 'label_text' => $d['label_text'] ?? null, 'match' => $d['match']]);
        $delivery->update(['photos' => collect($request->file('photos'))->map(fn ($f) => Images::store($f, 'warehouse/deliveries/'.$delivery->id, 1600))->all()]);

        return match ($d['match']) {
            'member' => $this->toMember($delivery, Member::findOrFail($d['member_id']), $me),
            'return' => $this->toReturn($delivery, Order::findOrFail($d['order_id']), $me),
            default => redirect()->route('w.home')->with('status', __('warehouse.delivery.unknown_done', ['place' => Moves::label('UNKNOWN')])),
        };
    }

    private function toMember(Delivery $delivery, Member $member, $me): RedirectResponse
    {
        Deliveries::toMember($delivery, $member, $me);

        return redirect()->route('w.home')->with('status', __('warehouse.delivery.member_done', ['name' => $member->companyName(), 'place' => Moves::label('A6-24H')]));
    }

    private function toReturn(Delivery $delivery, Order $order, $me): RedirectResponse
    {
        Deliveries::close($delivery, 'return', null, $me, ['order_id' => $order->id]);

        return redirect()->route('w.return', $order)->with('status', __('warehouse.delivery.return_done', ['order' => $order->number]));
    }

    // ------------------------------------------------------------------ office: who is this for?

    public function assign(Request $request, Delivery $delivery): RedirectResponse
    {
        abort_unless($delivery->status === 'open', 409);
        $d = $request->validate(['member_id' => ['nullable', 'integer', 'exists:members,id'], 'note' => ['nullable', 'string', 'max:500']]);
        if ($d['member_id'] ?? null) {
            Deliveries::toMember($delivery, Member::findOrFail($d['member_id']), $request->user());
        } else {
            $request->validate(['note' => ['required']]);
            Deliveries::close($delivery, 'unknown', $d['note'], $request->user());
        }

        return back()->with('status', __('warehouse.delivery.closed'));
    }
}
