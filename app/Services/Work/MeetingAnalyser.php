<?php

namespace App\Services\Work;

use App\Models\User;
use App\Models\Work\Meeting;
use App\Services\Media\Translator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads meeting notes and proposes the tasks: what has to be done, by whom, by when.
 * With Claude (key ANTHROPIC_API_KEY, the same as the translations) it understands
 * free text in any language; without it, simple rules pick up lines like
 * "Action: …", "TODO …", "- [ ] …" or "Elke: …".
 * Nothing becomes a task before a person has checked the proposals.
 */
class MeetingAnalyser
{
    /** @return array{by: string, summary: ?string, decisions: list<string>, tasks: list<array>}|null null = the notes could not be read */
    public static function analyse(Meeting $m): ?array
    {
        $staff = User::where('status', User::STATUS_ACTIVE)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'title', 'department']);
        $out = Translator::available() ? self::withClaude($m, $staff) : null;
        $out ??= self::withRules($m, $staff);
        if ($out === null) {
            return null;
        }
        $out['tasks'] = array_values(array_map(fn ($t) => self::clean($t, $m, $staff), $out['tasks']));

        return $out;
    }

    private static function withClaude(Meeting $m, Collection $staff): ?array
    {
        $people = $staff->map(fn ($u) => ['id' => $u->id, 'name' => trim($u->first_name.' '.$u->last_name), 'role' => trim(($u->title ?? '').' '.($u->department ?? ''))])->values();
        $context = [
            'meeting' => $m->title, 'date' => $m->held_on->toDateString(), 'weekday' => $m->held_on->format('l'),
            'kind' => $m->kind === 'customer' ? 'meeting with a customer' : 'internal meeting', 'customer' => $m->company?->name,
            'attendees' => $staff->whereIn('id', $m->attendees ?? [])->map(fn ($u) => trim($u->first_name.' '.$u->last_name))->values(),
        ];
        $prompt = 'You read meeting notes for CLUK, an event company in Belgium (equipment rental, a venue and a co-working space). '
            ."The notes come from a recording app and can be in Dutch, French, English or Spanish, or mixed.\n"
            ."Find every concrete action item: something a person has to do after the meeting. Skip general discussion and things already done.\n"
            ."For each task:\n"
            ."- title: short and starting with a verb, in the language of the notes (max 120 characters);\n"
            ."- details: one or two sentences of useful context, or empty;\n"
            ."- who: the name as written in the notes, or null;\n"
            ."- user_id: the id of the CLUK colleague from the list below who has to do it, matched on first name, full name or role; null when it is not one of them;\n"
            ."- for_customer: true when the customer (not CLUK) has to do it; CLUK will then follow it up;\n"
            ."- due: the date in YYYY-MM-DD when one is said or clearly meant (\"next Friday\", \"before the event\" = use the meeting date to work it out), else null.\n"
            ."Also give a summary (max 5 short sentences) and the decisions taken, both in the language of the notes.\n"
            ."Answer with JSON only: {\"summary\": \"...\", \"decisions\": [\"...\"], \"tasks\": [{\"title\": \"...\", \"details\": \"...\", \"who\": null, \"user_id\": null, \"for_customer\": false, \"due\": null}]}\n\n"
            .'CLUK colleagues: '.json_encode($people, JSON_UNESCAPED_UNICODE)."\n"
            .'Meeting: '.json_encode($context, JSON_UNESCAPED_UNICODE)."\n\n"
            ."Notes:\n<<<\n".mb_substr($m->notes, 0, 60000)."\n>>>";
        try {
            $res = Http::timeout(90)->withHeaders([
                'x-api-key' => (string) config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
            ])->post(Translator::URL, [
                'model' => config('services.anthropic.model'),
                'max_tokens' => 6000,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);
            if (! $res->successful()) {
                Log::warning('Meeting notes: Claude failed', ['http' => $res->status(), 'error' => mb_substr((string) $res->json('error.message'), 0, 200)]);

                return null;
            }
            $text = (string) collect($res->json('content', []))->where('type', 'text')->pluck('text')->implode('');
            $a = strpos($text, '{');
            $b = strrpos($text, '}');
            $json = $a !== false && $b !== false ? json_decode(substr($text, $a, $b - $a + 1), true) : null;
            if (! is_array($json) || ! isset($json['tasks']) || ! is_array($json['tasks'])) {
                Log::warning('Meeting notes: answer was not JSON');

                return null;
            }

            return ['by' => 'claude', 'summary' => is_string($json['summary'] ?? null) ? mb_substr($json['summary'], 0, 2000) : null,
                'decisions' => array_values(array_filter(array_map(fn ($d) => is_string($d) ? mb_substr(trim($d), 0, 300) : null, (array) ($json['decisions'] ?? [])))),
                'tasks' => array_values(array_filter((array) $json['tasks'], 'is_array'))];
        } catch (Throwable $e) {
            Log::warning('Meeting notes: Claude not reachable', ['error' => mb_substr($e->getMessage(), 0, 200)]);

            return null;
        }
    }

    /** Without Claude: lines that clearly are actions. */
    private static function withRules(Meeting $m, Collection $staff): array
    {
        $tasks = [];
        $names = $staff->flatMap(fn ($u) => [mb_strtolower($u->first_name) => $u->id, mb_strtolower(trim($u->first_name.' '.$u->last_name)) => $u->id])->all();
        foreach (preg_split('/\R/', $m->notes) as $line) {
            $l = trim($line);
            if ($l === '') {
                continue;
            }
            $who = null;
            $userId = null;
            if (preg_match('/^(?:[-*•]\s*)?(?:\[\s?\]|todo|to do|to-do|action(?: point)?|actie(?:punt)?|ap|taak|tâche|a faire|à faire|tarea)\s*[:\-–]?\s*(.+)$/iu', $l, $mm)) {
                $title = $mm[1];
            } elseif (preg_match('/^(?:[-*•]\s*)?([\p{L}][\p{L} \'-]{1,40}?)\s*[:\-–]\s*(.+)$/u', $l, $mm) && isset($names[mb_strtolower(trim($mm[1]))])) {
                $who = trim($mm[1]);
                $userId = $names[mb_strtolower($who)];
                $title = $mm[2];
            } else {
                continue;
            }
            // "(Elke)" or "@Elke" anywhere in the line names the person.
            if (! $userId && preg_match_all('/\(\s*([\p{L}][\p{L} \'-]{1,40}?)\s*\)|@([\p{L}][\p{L}\'-]{1,30})/u', $title, $all, PREG_SET_ORDER)) {
                foreach ($all as $hit) {
                    $n = trim($hit[1] !== '' ? $hit[1] : ($hit[2] ?? ''));
                    if (isset($names[mb_strtolower($n)])) {
                        $who = $n;
                        $userId = $names[mb_strtolower($n)];
                        $title = trim(preg_replace('/\s+/', ' ', str_replace($hit[0], '', $title)));
                        break;
                    }
                }
            }
            $tasks[] = ['title' => $title, 'details' => '', 'who' => $who, 'user_id' => $userId, 'for_customer' => false, 'due' => self::dateIn($title, $m->held_on)];
        }

        return ['by' => 'rules', 'summary' => null, 'decisions' => [], 'tasks' => $tasks];
    }

    /** A date written as 31/10, 31/10/2026 or 2026-10-31 in the text. */
    private static function dateIn(string $text, Carbon $from): ?string
    {
        if (preg_match('/\b(\d{4})-(\d{2})-(\d{2})\b/', $text, $d)) {
            return checkdate((int) $d[2], (int) $d[3], (int) $d[1]) ? "$d[1]-$d[2]-$d[3]" : null;
        }
        if (preg_match('/\b(\d{1,2})[\/.](\d{1,2})(?:[\/.](\d{2,4}))?\b/', $text, $d)) {
            $y = isset($d[3]) ? ((int) $d[3] < 100 ? 2000 + (int) $d[3] : (int) $d[3]) : $from->year;
            if (! checkdate((int) $d[2], (int) $d[1], $y)) {
                return null;
            }
            $date = Carbon::create($y, (int) $d[2], (int) $d[1]);
            if (! isset($d[3]) && $date->lt($from)) {
                $date->addYear();
            }

            return $date->toDateString();
        }

        return null;
    }

    /** One proposal in a safe shape: a known colleague, a real date, and who follows up a customer's task. */
    private static function clean(array $t, Meeting $m, Collection $staff): array
    {
        $title = mb_substr(trim(strip_tags((string) ($t['title'] ?? ''))), 0, 250);
        $uid = isset($t['user_id']) && $staff->contains('id', (int) $t['user_id']) ? (int) $t['user_id'] : null;
        $customer = (bool) ($t['for_customer'] ?? false);
        // Nobody named, or the customer's task: whoever follows it up (the account owner for a customer, else the writer).
        $follow = $m->kind === 'customer' && $m->company?->account_owner_id && $staff->contains('id', $m->company->account_owner_id) ? $m->company->account_owner_id : $m->created_by_id;
        $due = null;
        try {
            $due = ! empty($t['due']) ? Carbon::parse((string) $t['due'])->toDateString() : null;
        } catch (Throwable) {
        }

        return [
            'title' => $title === '' ? '—' : $title,
            'details' => mb_substr(trim(strip_tags((string) ($t['details'] ?? ''))), 0, 1000),
            'who' => isset($t['who']) && is_string($t['who']) ? mb_substr($t['who'], 0, 80) : null,
            'user_id' => $uid ?? $follow,
            'guessed' => $uid === null,
            'for_customer' => $customer,
            'due' => $due,
        ];
    }
}
