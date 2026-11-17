<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Equipment\Item;
use App\Models\Storage\StorageCheck;
use App\Models\Storage\StorageContract;
use App\Models\Storage\StoragePlace;
use App\Models\Warehouse\CheckProcedure;
use App\Models\Warehouse\Position;
use App\Services\Storage\CustomerStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Customer storage: contracts, places, their equipment, goods in and out, checks, monthly statements. */
class StorageController extends Controller
{
    private function work(Request $request): void
    {
        abort_unless($request->user()->canUseModule('warehouse', 2), 403);
    }

    public function index(): View
    {
        return view('warehouse.storage.index', [
            'contracts' => StorageContract::with('company')->withCount(['places' => fn ($q) => $q->whereNull('until'), 'pieces' => fn ($q) => $q->where('stored_pieces.status', 'in')])->orderByRaw("status = 'ended'")->orderBy('id')->get(),
            'companies' => Company::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->work($request);
        $d = $request->validate(['company_id' => ['required', 'exists:companies,id'], 'start_date' => ['required', 'date'], 'note' => ['nullable', 'string', 'max:300']]);
        $c = StorageContract::create($d + ['created_by_id' => $request->user()->id]);
        AuditLog::record('storage.contract_made', $c, ['company' => $c->company->name], $request->user());

        return redirect()->route('storage.show', $c)->with('status', __('storage.flash.made'));
    }

    public function show(StorageContract $contract): View
    {
        $contract->load(['company.accountOwner', 'places.position', 'items.pieces.checks', 'items.sameAs', 'items.checkProcedure', 'moves.user', 'statements.preInvoice']);
        $held = CustomerStorage::held();
        $month = today()->startOfMonth();

        return view('warehouse.storage.show', [
            'c' => $contract,
            'freePlaces' => Position::where('use', CustomerStorage::PLACE_USE)->where('active', true)->orderBy('code')->get()->reject(fn ($p) => isset($held[$p->code])),
            'procedures' => CheckProcedure::where('active', true)->orderBy('id')->get(),
            'ourItems' => Item::where('active', true)->where('kind', 'item')->orderBy('name')->get(['id', 'number', 'name']),
            'checks' => StorageCheck::with(['piece.item', 'checkedBy'])->whereHas('piece.item', fn ($q) => $q->where('contract_id', $contract->id))->latest('id')->limit(30)->get(),
            'preview' => CustomerStorage::lines($contract, $month, today(), app()->getLocale()),
            'rate' => CustomerStorage::hourRate(),
        ]);
    }

    public function addPlace(Request $request, StorageContract $contract): RedirectResponse
    {
        $this->work($request);
        $d = $request->validate(['position_id' => ['required', 'exists:warehouse_positions,id'], 'kind' => ['required', 'in:shelf,pallet'], 'from' => ['required', 'date']]);
        CustomerStorage::addPlace($contract, Position::findOrFail($d['position_id']), $d['kind'], Carbon::parse($d['from']), $request->user());

        return back()->with('status', __('storage.flash.place_added'));
    }

    public function endPlace(Request $request, StorageContract $contract, StoragePlace $place): RedirectResponse
    {
        $this->work($request);
        abort_unless($place->contract_id === $contract->id && ! $place->until, 404);
        CustomerStorage::endPlace($place, Carbon::parse($request->validate(['until' => ['required', 'date']])['until']), $request->user());

        return back()->with('status', __('storage.flash.place_ended'));
    }

    public function addItem(Request $request, StorageContract $contract): RedirectResponse
    {
        $this->work($request);
        $d = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'their_number' => ['nullable', 'string', 'max:60'],
            'same_as_item_id' => ['nullable', 'exists:equipment_items,id'], 'check_procedure_id' => ['nullable', 'exists:check_procedures,id'],
            'pieces' => ['required', 'integer', 'min:1', 'max:500'], 'serials' => ['nullable', 'string', 'max:10000'],
        ]);
        $serials = preg_split('/\R/', (string) ($d['serials'] ?? ''));
        CustomerStorage::addItem($contract, collect($d)->only(['name', 'their_number', 'same_as_item_id', 'check_procedure_id'])->all(), (int) $d['pieces'], $serials, $request->user());

        return back()->with('status', trans_choice('storage.flash.item_added', (int) $d['pieces'], ['n' => $d['pieces']]));
    }

    public function move(Request $request, StorageContract $contract): RedirectResponse
    {
        $this->work($request);
        $d = $request->validate([
            'kind' => ['required', 'in:in,out'], 'date' => ['required', 'date'], 'colis' => ['nullable', 'integer', 'min:0', 'max:9999'], 'pallets' => ['nullable', 'integer', 'min:0', 'max:999'],
            'pieces' => ['array'], 'pieces.*' => ['integer'], 'signed_name' => ['nullable', 'string', 'max:120'], 'note' => ['nullable', 'string', 'max:300'],
        ]);
        CustomerStorage::move($contract, $d['kind'], $d, $request->user());

        return back()->with('status', __('storage.flash.moved_'.$d['kind']));
    }

    public function statement(Request $request, StorageContract $contract): RedirectResponse
    {
        $this->work($request);
        $month = Carbon::parse($request->validate(['month' => ['required', 'date_format:Y-m']])['month'].'-01');
        $st = CustomerStorage::statement($contract, $month, $request->user());

        return back()->with('status', $st ? __('storage.flash.statement', ['number' => $st->preInvoice->number]) : __('storage.flash.nothing'));
    }

    public function end(Request $request, StorageContract $contract): RedirectResponse
    {
        $this->work($request);
        $until = Carbon::parse($request->validate(['end_date' => ['required', 'date']])['end_date']);
        foreach ($contract->places()->whereNull('until')->get() as $p) {
            CustomerStorage::endPlace($p, $until, $request->user());
        }
        $contract->update(['status' => 'ended', 'end_date' => $until]);
        AuditLog::record('storage.contract_ended', $contract, ['until' => $until->toDateString()], $request->user());

        return back()->with('status', __('storage.flash.ended'));
    }

    public function updateItem(Request $request, StorageContract $contract, \App\Models\Storage\StoredItem $item): RedirectResponse
    {
        $this->work($request);
        abort_unless($item->contract_id === $contract->id, 404);
        $item->update($request->validate(['same_as_item_id' => ['nullable', 'exists:equipment_items,id'], 'check_procedure_id' => ['nullable', Rule::exists('check_procedures', 'id')]]));

        return back()->with('status', __('storage.flash.saved'));
    }
}
