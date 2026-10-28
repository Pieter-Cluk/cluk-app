<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Website\Chat;
use App\Services\Website\Clukie;
use App\Services\Website\TeamChats;
use App\Services\Website\WebActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Sales › Chats: Clukie chats handed to the team. The team answers in the same chat. */
class ChatController extends Controller
{
    public const TABS = ['open', 'bot', 'closed'];

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'open';
        $chats = Chat::withCount('messages')
            ->when($tab === 'open', fn ($q) => $q->whereIn('status', ['waiting', 'staff']))
            ->when($tab !== 'open', fn ($q) => $q->where('status', $tab))
            ->orderByDesc('last_message_at')->paginate(40)->withQueryString();

        return view('sales.chats.index', [
            'chats' => $chats, 'tab' => $tab,
            'counts' => ['open' => Chat::whereIn('status', ['waiting', 'staff'])->count(), 'bot' => Chat::where('status', 'bot')->count(), 'closed' => Chat::where('status', 'closed')->count()],
        ]);
    }

    public function show(Request $request, Chat $chat): View
    {
        $chat->load(['messages', 'incoming']);

        return view('sales.chats.show', [
            'chat' => $chat,
            'staff' => \App\Models\User::whereIn('id', $chat->messages->pluck('user_id')->filter()->push($chat->staff_user_id)->filter()->unique())->get()->keyBy('id'),
            'act' => WebActivity::summary(TeamChats::visitorIds($chat)),
            'canWork' => $request->user()->canUseModule('sales', 2),
            'teamOpen' => Clukie::open(),
        ]);
    }

    /** New lines for the open chat screen (it checks every few seconds). */
    public function poll(Request $request, Chat $chat): JsonResponse
    {
        $after = (int) $request->query('after', 0);
        $lines = $chat->messages()->where('id', '>', $after)->get();

        return response()->json([
            'status' => $chat->status,
            'html' => $lines->map(fn ($m) => view('sales.chats._line', ['m' => $m, 'staff' => \App\Models\User::whereIn('id', [$m->user_id])->get()->keyBy('id')])->render())->implode(''),
            'last' => $lines->max('id') ?? $after,
        ]);
    }

    public function reply(Request $request, Chat $chat): RedirectResponse|JsonResponse
    {
        $d = $request->validate(['text' => ['required', 'string', 'max:4000']]);
        TeamChats::reply($chat, $request->user(), trim($d['text']));

        return $request->expectsJson() ? response()->json(['ok' => true]) : redirect()->route('chats.show', $chat);
    }

    public function close(Request $request, Chat $chat): RedirectResponse
    {
        TeamChats::close($chat, $request->user());

        return redirect()->route('chats.index')->with('status', __('chats.closed_flash'));
    }
}
