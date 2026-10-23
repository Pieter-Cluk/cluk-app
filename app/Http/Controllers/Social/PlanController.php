<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Social\Account;
use App\Models\Social\Plan;
use App\Models\Social\Post;
use App\Services\Social\Media;
use App\Services\Social\Social;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Media › Social media › Posts: write once, choose the accounts (with an own
 * text per account if wanted), add pictures or a video and a link (tracking
 * codes are added per platform), then save as draft, plan or publish now.
 * Planning and publishing need the right "Social media: publish".
 */
class PlanController extends Controller
{
    public function index(Request $request): View
    {
        $tab = in_array($request->query('s'), ['planned', 'drafts', 'published', 'site'], true) ? $request->query('s') : 'planned';
        if ($tab === 'site') {
            // Facebook and Instagram posts as they are on the platforms: tick which ones the website shows.
            $posts = Post::with('account')->whereHas('account', fn ($q) => $q->whereIn('platform', ['facebook', 'instagram']))
                ->whereNotNull('published_at')->latest('published_at')->paginate(40)->withQueryString();

            return view('social.plans', ['plans' => null, 'posts' => $posts, 'tab' => $tab, 'canPost' => SocialController::canPlan($request),
                'counts' => ['planned' => Plan::whereIn('status', ['scheduled', 'publishing'])->count(), 'drafts' => Plan::where('status', 'draft')->count()]]);
        }
        $plans = Plan::with(['targets.account', 'createdBy'])
            ->when($tab === 'planned', fn ($q) => $q->whereIn('status', ['scheduled', 'publishing'])->orderBy('scheduled_at'))
            ->when($tab === 'drafts', fn ($q) => $q->where('status', 'draft')->latest())
            ->when($tab === 'published', fn ($q) => $q->whereIn('status', ['done', 'partly', 'failed'])->latest('scheduled_at'))
            ->paginate(30)->withQueryString();

        return view('social.plans', ['plans' => $plans, 'tab' => $tab, 'canPost' => SocialController::canPlan($request),
            'counts' => ['planned' => Plan::whereIn('status', ['scheduled', 'publishing'])->count(), 'drafts' => Plan::where('status', 'draft')->count()]]);
    }

    public function create(Request $request): View
    {
        abort_unless(SocialController::canPlan($request), 403);

        return $this->form($request, new Plan(['body' => '']));
    }

    public function edit(Request $request, Plan $plan): View
    {
        abort_unless(SocialController::canPlan($request), 403);

        return $this->form($request, $plan->load('targets.account'));
    }

    private function form(Request $request, Plan $plan): View
    {
        return view('social.plan-form', [
            'plan' => $plan, 'accounts' => Account::where('status', '!=', 'off')->orderBy('platform')->get(),
            'canPublish' => $request->user()->hasRight('social.publish'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(SocialController::canPlan($request), 403);
        $plan = new Plan(['created_by_id' => $request->user()->id, 'status' => 'draft']);

        return $this->save($request, $plan);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        abort_unless(SocialController::canPlan($request), 403);
        abort_unless($plan->editable(), 409);

        return $this->save($request, $plan);
    }

    private function save(Request $request, Plan $plan): RedirectResponse
    {
        $d = $request->validate([
            'title' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:63000'], 'link_url' => ['nullable', 'url', 'max:500'],
            'accounts' => ['nullable', 'array'], 'accounts.*' => ['integer'], 'own' => ['nullable', 'array'], 'own.*' => ['nullable', 'string', 'max:63000'],
            'files' => ['nullable', 'array', 'max:10'], 'files.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime', 'max:'.Media::VIDEO_MAX_KB],
            'remove' => ['nullable', 'array'], 'remove.*' => ['integer'],
            'action' => ['required', 'in:draft,schedule,now'], 'scheduled_at' => ['nullable', 'date'],
        ]);
        if ($d['action'] !== 'draft' && ! $request->user()->hasRight('social.publish')) {
            abort(403);
        }
        // Pictures and video: what stays, what is added (removed files are deleted once saved).
        $removed = collect($plan->media ?? [])->filter(fn ($m, $i) => in_array($i, $d['remove'] ?? [], true));
        $media = collect($plan->media ?? [])->reject(fn ($m, $i) => in_array($i, $d['remove'] ?? [], true));
        foreach ($request->file('files', []) as $f) {
            if (! str_starts_with((string) $f->getMimeType(), 'video/') && $f->getSize() > Media::IMAGE_MAX_KB * 1024) {
                throw ValidationException::withMessages(['files' => __('social.errors.image_size')]);
            }
            $media->push(Media::store($f));
        }
        if ($media->where('type', 'video')->count() > 1 || ($media->where('type', 'video')->count() && $media->where('type', 'image')->count())) {
            throw ValidationException::withMessages(['files' => __('social.errors.one_video')]);
        }
        $accounts = Account::whereIn('id', $d['accounts'] ?? [])->where('status', '!=', 'off')->get();
        $when = $d['action'] === 'now' ? now() : ($d['action'] === 'schedule' ? Carbon::parse($d['scheduled_at'] ?? 'now', 'Europe/Brussels') : null);

        // Checks per platform before anything goes out.
        if ($d['action'] !== 'draft') {
            $errors = [];
            if ($accounts->isEmpty()) {
                $errors['accounts'] = __('social.errors.no_accounts');
            }
            if ($d['action'] === 'schedule' && (! $when || $when->lt(now()->subMinutes(5)))) {
                $errors['scheduled_at'] = __('social.errors.when');
            }
            foreach ($accounts as $a) {
                $cfg = config('social.platforms.'.$a->platform);
                $text = trim(($d['own'][$a->id] ?? '') ?: $d['body']);
                if (! $a->canPublish()) {
                    $errors['accounts'] = __('social.errors.cannot_publish', ['name' => $a->label()]);
                } elseif (mb_strlen($text) > $cfg['max']) {
                    $errors['body'] = __('social.errors.too_long', ['name' => $a->label(), 'max' => $cfg['max']]);
                } elseif ($cfg['media'] && $media->isEmpty()) {
                    $errors['files'] = __('social.errors.needs_media', ['name' => $a->label()]);
                }
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
        }

        $plan->fill(['title' => $d['title'], 'body' => $d['body'], 'link_url' => $d['link_url'] ?? null, 'media' => $media->values()->all(),
            'scheduled_at' => $when?->copy()->timezone(config('app.timezone')), 'status' => $d['action'] === 'draft' ? 'draft' : 'scheduled',
            'scheduled_by_id' => $d['action'] === 'draft' ? $plan->scheduled_by_id : $request->user()->id])->save();
        foreach ($removed as $m) {
            Storage::disk('local')->delete($m['path']);
        }
        $keep = $accounts->pluck('id')->all();
        $plan->targets()->whereNotIn('account_id', $keep)->delete();
        foreach ($accounts as $a) {
            $plan->targets()->updateOrCreate(['account_id' => $a->id], ['body' => trim($d['own'][$a->id] ?? '') ?: null, 'status' => 'pending', 'error' => null, 'tries' => 0, 'container_id' => null]);
        }
        AuditLog::record('social.plan_'.$d['action'], $plan, ['title' => $plan->title, 'accounts' => $accounts->map->label()->implode(', '), 'when' => $when?->format('d/m/Y H:i')], $request->user());

        if ($d['action'] === 'now') {
            $plan->update(['status' => 'publishing']);
            Social::publishDue();
            $plan->refresh();

            return redirect()->route('social.plans.show', $plan)->with('status', __('social.flash.published_'.($plan->status === 'done' ? 'ok' : 'check')));
        }

        return redirect()->route($d['action'] === 'draft' ? 'social.plans.edit' : 'social.plans.show', $plan)
            ->with('status', $d['action'] === 'draft' ? __('social.flash.draft') : __('social.flash.scheduled', ['when' => $when->timezone('Europe/Brussels')->format('d/m/Y H:i')]));
    }

    public function show(Plan $plan): View
    {
        return view('social.plan-show', ['plan' => $plan->load(['targets.account', 'createdBy', 'scheduledBy'])]);
    }

    /** Back to draft (only while nothing went out yet). */
    public function unschedule(Request $request, Plan $plan): RedirectResponse
    {
        abort_unless($plan->status === 'scheduled', 409);
        $plan->update(['status' => 'draft']);
        AuditLog::record('social.plan_unscheduled', $plan, ['title' => $plan->title], $request->user());

        return redirect()->route('social.plans.edit', $plan)->with('status', __('social.flash.unscheduled'));
    }

    /** Try the failed accounts of a post again. */
    public function retry(Request $request, Plan $plan): RedirectResponse
    {
        abort_unless(in_array($plan->status, ['partly', 'failed'], true), 409);
        $plan->targets()->where('status', 'failed')->update(['status' => 'pending', 'tries' => 0, 'error' => null]);
        $plan->update(['status' => 'publishing', 'scheduled_by_id' => $request->user()->id]);
        Social::publishDue();

        return back()->with('status', __('social.flash.retried'));
    }

    public function destroy(Request $request, Plan $plan): RedirectResponse
    {
        abort_unless(SocialController::canPlan($request), 403);
        abort_unless($plan->status === 'draft', 409);
        foreach ($plan->media ?? [] as $m) {
            Storage::disk('local')->delete($m['path']);
        }
        AuditLog::record('social.plan_deleted', null, ['title' => $plan->title], $request->user());
        $plan->delete();

        return redirect()->route('social.plans')->with('status', __('social.flash.deleted'));
    }

    /** Show a Facebook or Instagram post on the website, or not. */
    public function website(Request $request, Post $post): RedirectResponse
    {
        abort_unless(SocialController::canPlan($request), 403);
        $on = $request->boolean('on');
        $post->forceFill(['on_website' => $on])->save();
        AuditLog::record($on ? 'social.post_shown' : 'social.post_hidden', null, ['post' => mb_substr((string) $post->text, 0, 80), 'link' => $post->permalink], $request->user());

        return back()->with('status', __($on ? 'social.plans.site_shown' : 'social.plans.site_hidden'));
    }
}
