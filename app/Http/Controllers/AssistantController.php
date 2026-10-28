<?php

namespace App\Http\Controllers;

use App\Models\Assistant\AssistantChat;
use App\Services\Assistant\StaffClukie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Clukie in the platform: the colleague writes, Clukie answers (only what their rights allow). */
class AssistantController extends Controller
{
    public const MAX_MESSAGES = 120;

    public function message(Request $request): JsonResponse
    {
        $d = $request->validate(['token' => ['nullable', 'string', 'max:48'], 'after' => ['nullable', 'integer', 'min:0'], 'text' => ['required', 'string', 'max:2000'], 'page' => ['nullable', 'string', 'max:255']]);
        $chat = $this->chat($request, $d['token'] ?? null);
        $after = (int) ($d['after'] ?? 0);
        if ($chat->messages()->count() >= self::MAX_MESSAGES) {
            // A long conversation: Clukie starts a fresh one (the window takes the new token).
            $chat = AssistantChat::create(['token' => Str::random(40), 'user_id' => $request->user()->id, 'locale' => $request->user()->locale ?: 'en']);
            $after = 0;
        }
        $chat->say('visitor', trim($d['text']).(! empty($d['page']) ? "\n\n[I am on: ".Str::limit($d['page'], 200).']' : ''));
        (new StaffClukie($chat, $request->user()))->answer();

        return $this->out($chat->fresh(), $after);
    }

    public function poll(Request $request): JsonResponse
    {
        $d = $request->validate(['token' => ['required', 'string', 'max:48'], 'after' => ['nullable', 'integer', 'min:0']]);
        $chat = AssistantChat::where('token', $d['token'])->where('user_id', $request->user()->id)->first();
        abort_unless($chat, 404);

        return $this->out($chat, (int) ($d['after'] ?? 0));
    }

    private function chat(Request $request, ?string $token): AssistantChat
    {
        $u = $request->user();
        $chat = $token ? AssistantChat::where('token', $token)->where('user_id', $u->id)->first() : null;

        return $chat ?? AssistantChat::create(['token' => Str::random(40), 'user_id' => $u->id, 'locale' => $u->locale ?: 'en']);
    }

    /** Conversations are kept 3 months. */
    public static function prune(): int
    {
        return AssistantChat::where('updated_at', '<', now()->subMonths(3))->delete();
    }

    private function out(AssistantChat $chat, int $after, int $status = 200): JsonResponse
    {
        return response()->json([
            'token' => $chat->token, 'status' => 'bot', 'open' => true, 'next' => '',
            'messages' => $chat->messages()->where('id', '>', $after)->get()->map(function ($m) {
                $w = $m->forWeb();
                $w['body'] = preg_replace('/\n\n\[I am on: [^\]]*\]$/', '', $w['body']);

                return $w;
            })->values(),
            'actions' => [],
        ], $status);
    }
}
