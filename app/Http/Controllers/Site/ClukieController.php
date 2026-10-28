<?php

namespace App\Http\Controllers\Site;

use App\Models\Website\Chat;
use App\Services\Web\CustomerAuth;
use App\Services\Website\Clukie;
use App\Services\Website\Tracking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Clukie on the sales pages: the visitor writes, Clukie (or the team) answers. */
class ClukieController extends SiteController
{
    /** The visitor sends a line; Clukie answers unless the team has taken over. */
    public function message(Request $request): JsonResponse
    {
        $d = $request->validate([
            'token' => ['nullable', 'string', 'max:48'], 'after' => ['nullable', 'integer', 'min:0'],
            'text' => ['required', 'string', 'max:'.config('clukie.max_length')], 'page' => ['nullable', 'string', 'max:255'],
        ]);
        abort_unless(config('clukie.enabled'), 404);
        $chat = $this->chat($request, $d['token'] ?? null, true);
        $after = (int) ($d['after'] ?? 0);
        if ($chat->messages()->count() >= config('clukie.max_messages')) {
            if ($chat->status === 'bot') {
                Clukie::toTeam($chat, 'Long conversation: the visitor reached the chat limit.');
            }

            return $this->out($chat, $after, [], 422);
        }
        $chat->forceFill(['page' => Str::limit((string) ($d['page'] ?? ''), 250, '') ?: $chat->page])->save();
        $chat->say('visitor', trim($d['text']));
        $actions = [];
        if ($chat->status === 'bot') {
            $r = (new Clukie($chat, CustomerAuth::current()))->answer((string) $chat->page);
            $actions = $r['actions'];
            if ($r['handed'] && $chat->fresh()->incoming) {
                Tracking::linkRequest($request, $chat->fresh()->incoming, 'chat');
            }
        }

        return $this->out($chat->fresh(), $after, $actions);
    }

    /** New lines since the last one the browser has (the team answers here). */
    public function poll(Request $request): JsonResponse
    {
        $d = $request->validate(['token' => ['required', 'string', 'max:48'], 'after' => ['nullable', 'integer', 'min:0']]);
        $chat = Chat::where('token', $d['token'])->first();
        abort_unless($chat, 404);

        return $this->out($chat, (int) ($d['after'] ?? 0));
    }

    /** "Talk to the team" without Clukie (also when Clukie is not available). */
    public function team(Request $request): JsonResponse
    {
        $who = CustomerAuth::current();
        $d = $request->validate([
            'token' => ['nullable', 'string', 'max:48'], 'after' => ['nullable', 'integer', 'min:0'], 'page' => ['nullable', 'string', 'max:255'],
            'name' => [$who ? 'nullable' : 'required', 'string', 'max:150'], 'email' => [$who ? 'nullable' : 'required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:50'], 'question' => ['required', 'string', 'max:'.config('clukie.max_length')],
        ]);
        abort_unless(config('clukie.enabled'), 404);
        $chat = $this->chat($request, $d['token'] ?? null, true);
        $chat->fill(array_filter(['name' => $d['name'] ?? null, 'email' => $d['email'] ?? null, 'phone' => $d['phone'] ?? null,
            'page' => Str::limit((string) ($d['page'] ?? ''), 250, '') ?: null]))->save();
        $chat->say('visitor', trim($d['question']));
        $msg = Clukie::toTeam($chat, Str::limit(trim($d['question']), 300));
        Tracking::linkRequest($request, $msg, 'chat');

        return $this->out($chat->fresh(), (int) ($d['after'] ?? 0));
    }

    private function chat(Request $request, ?string $token, bool $make): Chat
    {
        $locale = $this->locale($request);
        $chat = $token ? Chat::where('token', $token)->first() : null;
        if (! $chat && $make) {
            $who = CustomerAuth::current();
            $chat = Chat::create([
                'token' => Str::random(40), 'locale' => $locale, 'status' => 'bot', 'customer_account_id' => $who?->id,
                'visitor_uuid' => Tracking::consented($request) ? Str::limit((string) $request->cookie('cluk_vid'), 36, '') : null,
                'name' => $who ? trim($who->first_name.' '.$who->last_name) : null, 'email' => $who?->email,
            ]);
        }
        abort_unless($chat && $chat->status !== 'closed', $chat ? 410 : 404);
        if ($chat->locale !== $locale) {
            $chat->forceFill(['locale' => $locale])->save();
        }

        return $chat;
    }

    private function out(Chat $chat, int $after, array $actions = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'token' => $chat->token, 'status' => $chat->status, 'open' => Clukie::open(), 'next' => Clukie::nextOpenText($chat->locale),
            'messages' => $chat->messages()->where('id', '>', $after)->get()->map->forWeb()->values(),
            'actions' => $actions,
        ], $status);
    }
}
