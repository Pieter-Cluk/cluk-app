<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Equipment\Item;
use App\Models\Warehouse\CheckProcedure;
use App\Services\Warehouse\Procedures;
use App\Support\Images;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Check Builder: the step-by-step procedures the Tech Check follows (right warehouse.build_checks). */
class CheckBuilderController extends Controller
{
    public function index(): View
    {
        return view('warehouse.checks.index', ['procedures' => CheckProcedure::with('current')->withCount('items')->orderBy('id')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate(['name_nl' => ['required_without:name_en', 'nullable', 'string', 'max:120'], 'name_en' => ['nullable', 'string', 'max:120']]);
        $p = Procedures::create(['nl' => $d['name_nl'] ?? '', 'en' => $d['name_en'] ?? ''], $request->user());

        return redirect()->route('checks.edit', $p)->with('status', __('checks.builder.created'));
    }

    public function edit(CheckProcedure $procedure): View
    {
        $procedure->load(['current', 'versions.publishedBy', 'items' => fn ($q) => $q->orderBy('name')]);

        return view('warehouse.checks.edit', [
            'p' => $procedure,
            'allItems' => Item::where('active', true)->where('kind', 'item')->where('serial', true)->orderBy('name')->get(['id', 'number', 'name', 'check_procedure_id']),
        ]);
    }

    public function update(Request $request, CheckProcedure $procedure): RedirectResponse
    {
        $d = $request->validate(['name_nl' => ['nullable', 'string', 'max:120'], 'name_en' => ['nullable', 'string', 'max:120'], 'active' => ['boolean']]);
        $procedure->update(['names' => ['nl' => $d['name_nl'] ?? '', 'en' => $d['name_en'] ?? ''], 'active' => $request->boolean('active')]);

        return back()->with('status', __('checks.builder.saved'));
    }

    public function saveStep(Request $request, CheckProcedure $procedure): RedirectResponse
    {
        $d = $request->validate([
            'key' => ['nullable', 'string', 'max:12'],
            'title_nl' => ['required_without:title_en', 'nullable', 'string', 'max:120'], 'title_en' => ['nullable', 'string', 'max:120'],
            'text_nl' => ['nullable', 'string', 'max:2000'], 'text_en' => ['nullable', 'string', 'max:2000'],
            'answer' => ['required', Rule::in(CheckProcedure::ANSWERS)],
            'choices' => ['nullable', 'string', 'max:2000'], 'costs' => ['nullable', 'string', 'max:3000'],
            'photos' => ['array', 'max:6'], 'photos.*' => Images::rule(), 'remove' => ['array'], 'remove.*' => ['integer'],
        ]);
        // One per line: "Dutch | English" (choices) and "Dutch | English | price" (costs; no price = priced later).
        $lines = fn (?string $t) => collect(preg_split('/\R/', (string) $t))->map(fn ($l) => array_map('trim', explode('|', $l)))->filter(fn ($p) => ($p[0] ?? '') !== '' || ($p[1] ?? '') !== '');
        $choices = $lines($d['choices'] ?? '')->map(fn ($p) => ['nl' => $p[0] ?? '', 'en' => ($p[1] ?? '') ?: ($p[0] ?? '')])->values()->all();
        if ($d['answer'] === 'choice' && count($choices) < 2) {
            return back()->withInput()->withErrors(['choices' => __('checks.builder.two_choices')]);
        }
        $costs = $lines($d['costs'] ?? '')->map(function ($p) {
            $num = fn ($v) => $v !== null && preg_match('/^(?:€)?\s*\d+([.,]\d{1,2})?$/u', trim((string) $v));
            $price = count($p) > 1 && $num(end($p)) ? array_pop($p) : null;

            return ['label' => ['nl' => $p[0] ?? '', 'en' => ($p[1] ?? '') ?: ($p[0] ?? '')],
                'cents' => $price !== null ? (int) round((float) str_replace(',', '.', preg_replace('/[^0-9,.]/', '', $price)) * 100) : null];
        })->values()->all();
        $key = Procedures::saveStep($procedure, $d['key'] ?? null, [
            'title' => ['nl' => $d['title_nl'] ?? '', 'en' => $d['title_en'] ?? ''], 'text' => ['nl' => $d['text_nl'] ?? '', 'en' => $d['text_en'] ?? ''],
            'answer' => $d['answer'], 'choices' => $choices, 'costs' => $costs,
        ], $request->file('photos', []), array_map('intval', $d['remove'] ?? []), $request->user());

        return redirect()->to(route('checks.edit', $procedure).'#step-'.$key)->with('status', __('checks.builder.step_saved'))->with('open', $key);
    }

    public function moveStep(Request $request, CheckProcedure $procedure, string $key): RedirectResponse
    {
        Procedures::moveStep($procedure, $key, $request->input('dir') === 'up' ? 'up' : 'down');

        return redirect()->to(route('checks.edit', $procedure).'#step-'.$key)->with('open', $key);
    }

    public function deleteStep(CheckProcedure $procedure, string $key): RedirectResponse
    {
        Procedures::deleteStep($procedure, $key);

        return back()->with('status', __('checks.builder.step_deleted'));
    }

    public function publish(Request $request, CheckProcedure $procedure): RedirectResponse
    {
        if (empty($procedure->draft_steps)) {
            return back()->withErrors(['publish' => __('checks.builder.no_steps')]);
        }
        $v = Procedures::publish($procedure, $request->validate(['note' => ['nullable', 'string', 'max:200']])['note'] ?? null, $request->user());

        return back()->with('status', __('checks.builder.published', ['n' => $v->version]));
    }

    public function copy(Request $request, CheckProcedure $procedure): RedirectResponse
    {
        return redirect()->route('checks.edit', Procedures::copy($procedure, $request->user()))->with('status', __('checks.builder.copied'));
    }

    public function assign(Request $request, CheckProcedure $procedure): RedirectResponse
    {
        $d = $request->validate(['items' => ['required', 'array'], 'items.*' => ['integer', 'exists:equipment_items,id']]);
        $n = Procedures::assign($procedure, $d['items'], $request->user());

        return back()->with('status', trans_choice('checks.builder.assigned', $n, ['n' => $n]));
    }

    public function unassign(CheckProcedure $procedure, Item $item): RedirectResponse
    {
        if ($item->check_procedure_id === $procedure->id) {
            $item->update(['check_procedure_id' => null]);
        }

        return back();
    }

    /** A reference photo of a step (staff only). */
    public function photo(Request $request)
    {
        $p = (string) $request->query('p');
        abort_unless(str_starts_with($p, 'warehouse/procedures/') && ! str_contains($p, '..') && Storage::disk('local')->exists($p), 404);

        return response()->file(Storage::disk('local')->path($p), ['Cache-Control' => 'private, max-age=86400']);
    }
}
