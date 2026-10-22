<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Rental\SubhireLine;
use App\Models\Rental\SubhireRequest;
use App\Support\Images;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sub-hired equipment on the phone. It is not ours, so there is nothing to scan:
 * the crew counts what came in (or goes back) and takes photos as proof of what
 * was there and in which state.
 */
class PhoneSubhireController extends Controller
{
    /** in = from the partner to us, back = from us to the partner. */
    public const DIRS = ['in', 'back'];

    public function show(SubhireRequest $subhire, string $dir): View
    {
        abort_unless($subhire->status === 'accepted', 404);
        abort_if($dir === 'back' && ! $subhire->received_at, 404);

        return view('warehouse.phone.subhire', ['r' => $subhire->load(['supplier', 'order.company', 'lines', 'receivedBy', 'handedBackBy']), 'dir' => $dir]);
    }

    public function count(Request $request, SubhireRequest $subhire, string $dir, SubhireLine $line): RedirectResponse
    {
        abort_unless($line->request_id === $subhire->id && $subhire->status === 'accepted', 404);
        $field = $dir === 'in' ? 'received_qty' : 'back_qty';
        abort_if($dir === 'in' ? $subhire->received_at : $subhire->handed_back_at, 409);
        $d = $request->validate(['delta' => ['required', 'integer', 'between:-1000,1000']]);
        $max = $dir === 'in' ? $line->qty : $line->received_qty;
        $line->update([$field => max(0, min($max, $line->{$field} + (int) $d['delta']))]);

        return back();
    }

    public function photo(Request $request, SubhireRequest $subhire, string $dir): RedirectResponse
    {
        abort_unless($subhire->status === 'accepted', 404);
        $request->validate(['photos' => ['required', 'array', 'max:8'], 'photos.*' => Images::rule()]);
        $field = $dir === 'in' ? 'in_photos' : 'out_photos';
        $photos = $subhire->{$field} ?? [];
        foreach ($request->file('photos', []) as $f) {
            $photos[] = Images::store($f, 'warehouse/subhire/'.$subhire->id.'/'.$dir, 1600);
        }
        $subhire->update([$field => $photos]);

        return back();
    }

    /** Close the step: everything counted, photos taken; a shortfall needs a note. */
    public function done(Request $request, SubhireRequest $subhire, string $dir): RedirectResponse
    {
        abort_unless($subhire->status === 'accepted', 404);
        $d = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $photos = $dir === 'in' ? $subhire->in_photos : $subhire->out_photos;
        if (empty($photos)) {
            throw ValidationException::withMessages(['photos' => __('warehouse.errors.photo_needed')]);
        }
        $lines = $subhire->lines;
        $short = $dir === 'in' ? $lines->filter(fn ($l) => $l->received_qty < $l->qty) : $lines->filter(fn ($l) => $l->back_qty < $l->received_qty);
        if ($short->isNotEmpty() && blank($d['note'] ?? null)) {
            throw ValidationException::withMessages(['note' => __('warehouse.sub.note_needed')]);
        }
        $data = $dir === 'in'
            ? ['received_at' => now(), 'received_by_id' => $request->user()->id, 'in_note' => $d['note'] ?? null]
            : ['handed_back_at' => now(), 'handed_back_by_id' => $request->user()->id, 'out_note' => $d['note'] ?? null];
        $subhire->update($data);
        AuditLog::record('warehouse.subhire_'.$dir, $subhire, [
            'pieces' => $dir === 'in' ? $lines->sum('received_qty').' / '.$lines->sum('qty') : $lines->sum('back_qty').' / '.$lines->sum('received_qty'),
            'photos' => count($photos), 'note' => $d['note'] ?? null,
        ], $request->user());

        return redirect()->route('w.home')->with('status', __('warehouse.sub.done_'.$dir, ['partner' => $subhire->supplier->name]));
    }

    public function media(SubhireRequest $subhire, string $dir, int $i)
    {
        $path = ($dir === 'in' ? $subhire->in_photos : $subhire->out_photos)[$i] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, max-age=86400']);
    }

    /** Accepted sub-hire still to come in (soon), and sub-hire to bring back. */
    public static function openTasks(int $days): array
    {
        $q = SubhireRequest::with(['supplier', 'order', 'lines'])->where('status', 'accepted');

        return [
            'in' => (clone $q)->whereNull('received_at')->where(fn ($w) => $w->whereNull('delivery_at')->orWhere('delivery_at', '<=', today()->addDays($days)->endOfDay()))->orderBy('delivery_at')->get(),
            'back' => (clone $q)->whereNotNull('received_at')->whereNull('handed_back_at')->where(fn ($w) => $w->where('return_at', '<=', today()->addDays($days)->endOfDay())
                ->orWhereHas('order', fn ($o) => $o->whereNotNull('returned_at')))->orderBy('return_at')->get(),
        ];
    }

    public static function rule(): array
    {
        return [Rule::in(self::DIRS)];
    }
}
