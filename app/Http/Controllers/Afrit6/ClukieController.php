<?php

namespace App\Http\Controllers\Afrit6;

use App\Http\Controllers\Controller;
use App\Models\Afrit6\MemberChat;
use App\Services\Afrit6\MemberClukie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Clukie in the Afrit 6 member portal: the member writes, Clukie answers (Afrit 6 only; prepares, never books). */
class ClukieController extends Controller
{
    public const MAX_MESSAGES = 80;

    public function message(Request $request): JsonResponse
    {
        $d = $request->validate(['token' => ['nullable', 'string', 'max:48'], 'after' => ['nullable', 'integer', 'min:0'], 'text' => ['required', 'string', 'max:1500'], 'page' => ['nullable', 'string', 'max:255']]);
        $member = $request->user('member');
        $chat = ($d['token'] ?? null) ? MemberChat::where('token', $d['token'])->where('member_id', $member->id)->first() : null;
        $after = (int) ($d['after'] ?? 0);
        if (! $chat || $chat->messages()->count() >= self::MAX_MESSAGES) {
            $chat = MemberChat::create(['token' => Str::random(40), 'member_id' => $member->id, 'locale' => app()->getLocale()]);
            $after = 0;
        }
        $chat->forceFill(['locale' => app()->getLocale()])->save();
        $chat->say('visitor', trim($d['text']).(! empty($d['page']) ? "\n\n[I am on: ".Str::limit($d['page'], 200).']' : ''));
        (new MemberClukie($chat, $member))->answer();

        return $this->out($chat->fresh(), $after);
    }

    public function poll(Request $request): JsonResponse
    {
        $d = $request->validate(['token' => ['required', 'string', 'max:48'], 'after' => ['nullable', 'integer', 'min:0']]);
        $chat = MemberChat::where('token', $d['token'])->where('member_id', $request->user('member')->id)->first();
        abort_unless($chat, 404);

        return $this->out($chat, (int) ($d['after'] ?? 0));
    }

    /** Conversations are kept 3 months. */
    public static function prune(): int
    {
        return MemberChat::where('updated_at', '<', now()->subMonths(3))->delete();
    }

    private function out(MemberChat $chat, int $after): JsonResponse
    {
        return response()->json([
            'token' => $chat->token, 'status' => 'bot', 'open' => true, 'next' => '',
            'messages' => $chat->messages()->where('id', '>', $after)->get()->map(function ($m) {
                $w = $m->forWeb();
                $w['body'] = preg_replace('/\n\n\[I am on: [^\]]*\]$/', '', $w['body']);

                return $w;
            })->values(),
            'actions' => [],
        ]);
    }
}
