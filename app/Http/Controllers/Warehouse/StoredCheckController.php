<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Storage\StorageCheck;
use App\Models\Storage\StoredPiece;
use App\Services\Warehouse\Checks;
use App\Services\Warehouse\TechCheck;
use App\Support\Images;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Phone: the Tech Check on a storage customer's piece (scan its ST- label). */
class StoredCheckController extends Controller
{
    public function show(StoredPiece $piece): View|RedirectResponse
    {
        $piece->load('item.contract.company', 'item.checkProcedure.current.procedure');
        $check = StorageCheck::openFor($piece);
        $v = TechCheck::versionForStored($piece, $check);
        if (! $v) {
            return redirect()->route('w.scan')->withErrors(['code' => __('storage.phone.no_procedure', ['code' => $piece->code])]);
        }

        $check = $check->fresh();

        return view('warehouse.phone.tech', ['piece' => $piece, 'check' => $check, 'v' => $v, 'kit' => TechCheck::kit($check), 'onKit' => TechCheck::proceduresDone($check, $v), 'onView' => ! TechCheck::viewDone($check), 'done' => TechCheck::complete($check, $v)]);
    }

    public function step(Request $request, StoredPiece $piece): RedirectResponse
    {
        $d = $request->validate([
            'answer' => ['nullable', 'string', 'max:10'], 'value' => ['nullable', 'string', 'max:120'], 'note' => ['nullable', 'string', 'max:500'],
            'costs' => ['array'], 'costs.*' => ['integer'], 'missing' => ['array'], 'missing.*' => ['nullable', 'integer', 'min:0', 'max:999'], 'dirt' => ['nullable', 'integer'], 'broken' => ['nullable', 'in:no,yes'], 'extra_label' => ['nullable', 'string', 'max:120'], 'extra_amount' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'photos' => ['array', 'max:6'], 'photos.*' => Images::rule(),
        ]);
        $check = StorageCheck::openFor($piece);
        $v = TechCheck::versionForStored($piece, $check);
        abort_unless($v, 404);
        $onView = ! TechCheck::viewDone($check);
        $onKit = TechCheck::proceduresDone($check, $v);
        if ($request->boolean('photo_only')) {
            Checks::addPhotos($check, $request->file('photos', []), $onView ? 'view' : ($onKit ? 'kit' : 'step:'.($v->steps[$check->step]['key'] ?? 'x')));

            return back()->withInput();
        }
        if ($onView) {
            TechCheck::answerView($check, $request->only(['dirt', 'broken', 'note', 'extra_label', 'extra_amount']), $request->file('photos', []));
            if ($request->boolean('stop')) {
                return redirect()->route('w.tech')->with('status', __('checks.run.view_saved', ['code' => $check->asset?->code ?? $check->piece?->code]));
            }
        } elseif ($onKit) {
            Checks::addPhotos($check, $request->file('photos', []), 'kit');
            TechCheck::answerKit($check, (array) $request->input('missing', []));
        } else {
            TechCheck::answer($check, $v, $d, $request->file('photos', []));
        }

        return redirect()->route('w.stored', $piece);
    }

    public function back(StoredPiece $piece): RedirectResponse
    {
        TechCheck::back(StorageCheck::openFor($piece));

        return redirect()->route('w.stored', $piece);
    }

    /** Done: who did it (CLUK: billed at the minutes, by default the procedure's duration) and the result. */
    public function finish(Request $request, StoredPiece $piece): RedirectResponse
    {
        $d = $request->validate(['done_by' => ['required', 'in:cluk,customer'], 'minutes' => ['nullable', 'integer', 'min:1', 'max:600']]);
        $check = StorageCheck::openFor($piece);
        $v = $check->procedureVersion;
        if (! $v || ! TechCheck::complete($check, $v)) {
            throw ValidationException::withMessages(['steps' => __('checks.run.not_done')]);
        }
        $broken = collect($check->answers)->contains(fn ($a) => ($a['answer'] ?? null) === 'fail');
        $minutes = $d['done_by'] === 'cluk' ? (int) ($d['minutes'] ?? $v->procedure->minutes ?? 15) : null;
        $check->update(['done_by' => $d['done_by'], 'minutes' => $minutes, 'result' => $broken ? 'repair' : 'ok', 'finished_at' => now(), 'checked_by_id' => $request->user()->id]);
        AuditLog::record('storage.checked', $piece->item->contract, ['code' => $piece->code, 'by' => $d['done_by'], 'minutes' => $minutes, 'result' => $check->result], $request->user());

        return redirect()->route('w.tech')->with('status', __($broken ? 'storage.phone.done_repair' : 'storage.phone.done_ok', ['code' => $piece->code]));
    }

    public function photo(StorageCheck $check, int $i)
    {
        $path = $check->photos[$i]['path'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, max-age=86400']);
    }
}
