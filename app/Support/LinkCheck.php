<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Clicks through the platform (and the website) like a person: opens a page, follows every link on it to the
 * app's own pages, and reports pages that fail, links to nothing, and texts that show as a raw translation key.
 *
 * Safe on the live system: every page runs inside a database transaction that is rolled back, mail goes to an
 * array, and no outside service is called. Links that do something (log out, connect, scan, print labels,
 * download, sync…) are not followed. Of pages with an id in the address, only a few per kind are opened.
 */
class LinkCheck
{
    /** Links that act instead of show. */
    private const SKIP = '#/(logout|log-out|connect|callback|unsubscribe|verify|labels?|scan|call|sync|refresh|export|download|print|backup|impersonate|switch|locale|lang|oauth|auth/|i/|w/|go/|track|file|photo|image|media/[a-z]+/\d+/(image|pdf)|pdf|xml|ubl|ics|csv|xlsx|zip)(/|\?|$)#i';

    /** @var array<string, array{status: int, from: string, error?: string, keys?: array}> */
    public array $problems = [];

    public int $opened = 0;

    /** Links still waiting when the limit was reached. */
    public int $left = 0;

    /** @var array<int, string> */
    public array $visited = [];

    /** @var array<string, int> */
    private array $perKind = [];

    public function __construct(private readonly int $max = 600, private readonly int $perPattern = 3) {}

    /**
     * @param  array<int, string>  $starts  absolute URLs
     */
    public function run(?Authenticatable $as, array $starts, string $guard = 'web'): self
    {
        config(['mail.default' => 'array', 'cluk.web_jobs' => false, 'queue.default' => 'sync']);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('', 503)]);
        $hosts = collect($starts)->map(fn ($u) => parse_url($u, PHP_URL_HOST))->unique()->all();
        $queue = array_map(fn ($u) => [$u, 'start'], $starts);
        $seen = [];
        $prefixes = collect(glob(lang_path('en/*.php')))->map(fn ($p) => basename($p, '.php'))->implode('|');

        while ($queue && $this->opened < $this->max) {
            [$url, $from] = array_shift($queue);
            $url = strtok($url, '#');
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $kind = preg_replace('#/\d+#', '/{id}', parse_url($url, PHP_URL_PATH) ?: '/').(parse_url($url, PHP_URL_QUERY) ? '?' : '');
            if (($this->perKind[$kind] = ($this->perKind[$kind] ?? 0) + 1) > ($kind === rtrim($kind, '?') ? $this->perPattern : 6) && (str_contains($kind, '{id}') || str_ends_with($kind, '?'))) {
                continue;
            }
            [$status, $html, $error] = $this->open($url, $as, $guard);
            $this->visited[] = $url;
            $this->opened++;
            if ($status >= 500 || $status === 404 || ($status >= 400 && $status !== 403 && $status !== 419)) {
                $this->problems[$url] = ['status' => $status, 'from' => $from] + ($error ? ['error' => $error] : []);

                continue;
            }
            if ($status >= 300 || $html === '') {
                continue;
            }
            // Raw translation keys in the visible text (e.g. "equipment.fields.weight").
            $text = strip_tags(preg_replace('#<(script|style|textarea)[^>]*>.*?</\1>#si', '', $html));
            if (preg_match_all('/(?<![\w@\/.-])('.$prefixes.')\.[a-z_]+(?:\.[a-z_]+)*(?![\w@\/-])/', $text, $m)) {
                $keys = array_values(array_filter(array_unique($m[0]), fn ($k) => ! preg_match('/\.(live|be|com|nl|fr|es|eu|org|net|app|io|de|uk|lu)$/', $k)));
                if ($keys) {
                    $this->problems[$url] = ['status' => $status, 'from' => $from, 'keys' => $keys];
                }
            }
            preg_match_all('/<a\s[^>]*href="([^"#][^"]*)"/i', $html, $links);
            foreach ($links[1] as $href) {
                $href = html_entity_decode($href);
                if (str_starts_with($href, '/')) {
                    $href = rtrim(preg_replace('#^(https?://[^/]+).*$#', '$1', $url), '/').$href;
                }
                $host = parse_url($href, PHP_URL_HOST);
                if (! $host || ! in_array($host, $hosts, true) || preg_match(self::SKIP, (string) parse_url($href, PHP_URL_PATH)) || str_contains($href, 'signature=')) {
                    continue;
                }
                if (! isset($seen[strtok($href, '#')])) {
                    $queue[] = [$href, $url];
                }
            }
        }

        $this->left = count($queue);

        return $this;
    }

    /** @return array{0: int, 1: string, 2: ?string} */
    private function open(string $url, ?Authenticatable $as, string $guard): array
    {
        $kernel = app(Kernel::class);
        DB::beginTransaction();
        try {
            Auth::forgetGuards();
            if ($as) {
                Auth::guard($guard)->setUser($as);
            }
            $request = Request::create($url, 'GET');
            $response = $kernel->handle($request);
            $status = $response->getStatusCode();
            $error = null;
            if ($status >= 500 && isset($response->exception)) {
                $e = $response->exception;
                $error = class_basename($e).': '.Str::limit($e->getMessage(), 220).' @ '.str_replace(base_path().'/', '', $e->getFile()).':'.$e->getLine();
            }
            $type = (string) $response->headers->get('Content-Type');

            return [$status, str_contains($type, 'html') ? (string) $response->getContent() : '', $error];
        } catch (Throwable $e) {
            return [500, '', class_basename($e).': '.Str::limit($e->getMessage(), 220)];
        } finally {
            DB::rollBack();
        }
    }

    /** @return array<int, string> one line per problem */
    public function lines(): array
    {
        return collect($this->problems)->map(fn ($p, $url) => $p['status'].' '.$url.(isset($p['error']) ? ' · '.$p['error'] : '').(isset($p['keys']) ? ' · raw keys: '.implode(', ', array_slice($p['keys'], 0, 6)) : '').' · from '.$p['from'])->values()->all();
    }
}
