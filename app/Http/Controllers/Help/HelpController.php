<?php

namespace App\Http\Controllers\Help;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Help\Article;
use App\Models\Help\HelpFile;
use App\Models\Help\Question;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Services\Help\Search;
use App\Services\Media\Translator;
use App\Support\InLocale;
use App\Support\Navigation;
use App\Support\SafeMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Help & shortcuts: type your problem, get the articles that match, read the
 * full explanation. Forms and guides to download. Still stuck? Ask the admin;
 * the answer comes back by email and can become a new article.
 */
class HelpController extends Controller
{
    /** Key presses that work on every screen: [keys, menu key or null, text key]. */
    public const SHORTCUTS = [
        ['?', 'help', 'help'], ['g d', 'dashboard', 'dashboard'], ['g c', 'companies', 'companies'], ['g q', 'sales', 'sales'],
        ['g o', 'orders', 'orders'], ['g h', 'dryhire', 'dryhire'], ['g w', 'warehouse', 'warehouse'], ['g p', 'preinvoice', 'preinvoice'],
    ];

    public static function canEdit(?User $u): bool
    {
        return $u && ($u->hasRight('help.manage') || $u->hasRight('users.manage'));
    }

    public function index(Request $request): View
    {
        $u = $request->user();
        $q = trim((string) $request->query('q', ''));
        $topic = in_array($request->query('topic'), Article::TOPICS, true) ? $request->query('topic') : null;
        $all = Article::where('active', true)->orderBy('sort')->orderBy('id')->get();
        $list = $topic ? $all->where('topic', $topic)->values() : $all;
        if ($q !== '') {
            $list = Search::rank($list, $q);
        }

        return view('help.index', [
            'q' => $q, 'topic' => $topic, 'articles' => $list, 'all' => $all,
            'topics' => $all->pluck('topic')->unique()->sortBy(fn ($t) => array_search($t, Article::TOPICS, true))->values(),
            'files' => HelpFile::orderBy('title')->get(),
            'mine' => Question::where('user_id', $u->id)->latest()->limit(5)->get(),
            'canEdit' => self::canEdit($u),
            'openQuestions' => self::canEdit($u) ? Question::where('status', 'open')->count() : 0,
            'shortcuts' => self::shortcuts($u),
        ]);
    }

    public function show(Request $request, Article $article): View
    {
        abort_unless($article->active || self::canEdit($request->user()), 404);
        $article->increment('views');

        return view('help.show', [
            'a' => $article,
            'related' => Article::where('active', true)->where('topic', $article->topic)->whereKeyNot($article->id)->orderBy('sort')->limit(4)->get(),
            'canEdit' => self::canEdit($request->user()),
        ]);
    }

    public function ask(Request $request): RedirectResponse
    {
        $d = $request->validate(['question' => ['required', 'string', 'min:5', 'max:2000'], 'page' => ['nullable', 'string', 'max:255']]);
        $u = $request->user();
        $question = Question::create(['user_id' => $u->id, 'question' => $d['question'], 'page' => $d['page'] ?? null, 'status' => 'open']);
        foreach (User::where('status', User::STATUS_ACTIVE)->get()->filter(fn (User $a) => self::canEdit($a) && $a->isNot($u)) as $admin) {
            $loc = $admin->locale ?: 'en';
            SafeMail::notify($admin, InLocale::run($loc, fn () => new Confirmation(
                subject: __('help.mail.question_subject', ['name' => $u->name]),
                greeting: __('mails.hello', ['name' => $admin->first_name]),
                intro: [__('help.mail.question_intro', ['name' => $u->name])],
                details: [__('help.question') => $question->question] + ($question->page ? [__('help.page') => $question->page] : []),
                button: [__('help.mail.answer_button'), route('help.manage')],
            ))->locale($loc));
        }

        return back()->with('status', __('help.asked'));
    }

    public function file(HelpFile $file): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }

    // ── For the admin ────────────────────────────────────────────────────────

    public function manage(Request $request): View
    {
        return view('help.manage', [
            'questions' => Question::with(['user', 'answeredBy'])->orderByRaw("status = 'open' desc")->latest()->limit(100)->get(),
            'articles' => Article::orderBy('topic')->orderBy('sort')->get(),
            'files' => HelpFile::with('uploadedBy')->orderBy('title')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $a = new Article(['topic' => 'start', 'active' => true]);
        if ($qid = $request->query('from')) {
            $from = Question::find($qid);
            $a->title = ['en' => Str::limit((string) $from?->question, 120, '')];
            $a->body = ['en' => (string) $from?->answer];
        }

        return view('help.form', ['a' => $a, 'canTranslate' => Translator::available(), 'fromQuestion' => $qid]);
    }

    public function edit(Article $article): View
    {
        return view('help.form', ['a' => $article, 'canTranslate' => Translator::available(), 'fromQuestion' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $a = new Article;
        $this->fill($request, $a);
        if ($qid = $request->input('from_question')) {
            Question::whereKey($qid)->update(['article_id' => $a->id]);
        }

        return redirect()->route('help.show', $a)->with('status', __('help.saved'));
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        $this->fill($request, $article);

        return redirect()->route('help.show', $article)->with('status', __('help.saved'));
    }

    public function destroy(Article $article): RedirectResponse
    {
        AuditLog::record('help.article_deleted', null, ['title' => $article->t('title', 'en')], request()->user());
        $article->delete();

        return redirect()->route('help.manage')->with('status', __('help.deleted'));
    }

    private function fill(Request $request, Article $a): void
    {
        $locales = array_keys(config('cluk.locales'));
        $rules = ['topic' => ['required', Rule::in(Article::TOPICS)], 'keywords' => ['nullable', 'string', 'max:500'], 'link_path' => ['nullable', 'string', 'max:255', 'regex:#^/[A-Za-z0-9/_\-?=&\#.]*$#'],
            'active' => ['nullable', 'boolean'], 'translate' => ['nullable', 'boolean'], 'title.en' => ['required', 'string', 'max:160']];
        foreach ($locales as $l) {
            $rules["title.$l"] ??= ['nullable', 'string', 'max:160'];
            $rules["intro.$l"] = ['nullable', 'string', 'max:500'];
            $rules["body.$l"] = ['nullable', 'string', 'max:10000'];
        }
        $d = $request->validate($rules);
        $title = array_filter($d['title'] ?? [], 'filled');
        $intro = array_filter($d['intro'] ?? [], 'filled');
        $body = array_filter($d['body'] ?? [], 'filled');

        // Fill the empty languages from English with Claude, when asked.
        if (! empty($d['translate']) && Translator::available()) {
            $tr = (new Translator)->translate(['title' => $title['en'], 'intro' => $intro['en'] ?? '', 'body' => $body['en'] ?? '']) ?? [];
            foreach ($tr as $loc => $f) {
                $title[$loc] ??= $f['title'] ?? null;
                isset($f['intro']) && ($intro[$loc] ??= $f['intro']);
                isset($f['body']) && ($body[$loc] ??= $f['body']);
            }
        }

        if (! $a->exists) {
            $base = Str::slug($title['en']) ?: 'article';
            $slug = $base;
            for ($i = 2; Article::where('slug', $slug)->exists(); $i++) {
                $slug = $base.'-'.$i;
            }
            $a->slug = Str::limit($slug, 80, '');
            $a->sort = (int) Article::max('sort') + 1;
        }
        $a->fill(['topic' => $d['topic'], 'title' => array_filter($title), 'intro' => $intro ?: null, 'body' => $body ?: null, 'keywords' => $d['keywords'] ?? null,
            'link_path' => $d['link_path'] ?? null, 'active' => (bool) ($d['active'] ?? false), 'updated_by_id' => $request->user()->id])->save();
        AuditLog::record('help.article_saved', $a, ['title' => $title['en']], $request->user());
    }

    public function answer(Request $request, Question $question): RedirectResponse
    {
        $d = $request->validate(['answer' => ['required', 'string', 'max:5000'], 'article_id' => ['nullable', 'integer', 'exists:help_articles,id']]);
        $question->update(['answer' => $d['answer'], 'article_id' => $d['article_id'] ?? $question->article_id, 'status' => 'answered', 'answered_by_id' => $request->user()->id, 'answered_at' => now()]);
        $to = $question->user;
        if ($to?->isActive()) {
            $loc = $to->locale ?: 'en';
            SafeMail::notify($to, InLocale::run($loc, fn () => new Confirmation(
                subject: __('help.mail.answer_subject'),
                greeting: __('mails.hello', ['name' => $to->first_name]),
                intro: [__('help.mail.answer_intro', ['name' => $request->user()->name])],
                details: [__('help.question') => $question->question, __('help.answer') => $question->answer],
                button: $question->article ? [__('help.mail.read_article'), route('help.show', $question->article)] : [__('help.mail.open_help'), route('help.index')],
                signer: $request->user(),
            ))->locale($loc));
        }

        return back()->with('status', __('help.answered'));
    }

    public function close(Question $question): RedirectResponse
    {
        $question->update(['status' => 'closed']);

        return back();
    }

    public function upload(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'title' => ['required', 'string', 'max:160'], 'note' => ['nullable', 'string', 'max:255'], 'topic' => ['nullable', Rule::in(Article::TOPICS)],
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg,zip,txt,csv'],
        ]);
        $f = $request->file('file');
        $path = $f->store('help', 'local');
        HelpFile::create(['title' => $d['title'], 'note' => $d['note'] ?? null, 'topic' => $d['topic'] ?? null, 'path' => $path,
            'original_name' => $f->getClientOriginalName(), 'size' => (int) $f->getSize(), 'uploaded_by_id' => $request->user()->id]);

        return back()->with('status', __('help.uploaded'));
    }

    public function removeFile(HelpFile $file): RedirectResponse
    {
        Storage::disk('local')->delete($file->path);
        $file->delete();

        return back()->with('status', __('help.deleted'));
    }

    /** @return list<array{keys: string, what: string, url: string}> */
    public static function shortcuts(User $u): array
    {
        $out = [];
        foreach (self::SHORTCUTS as [$keys, $nav, $text]) {
            if (Navigation::allows($u, $nav)) {
                $item = Navigation::item($nav);
                $out[] = ['keys' => $keys, 'what' => __('nav.items.'.$text), 'url' => url($item['path'])];
            }
        }

        return $out;
    }
}
