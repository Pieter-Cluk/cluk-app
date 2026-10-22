<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Media\Message;
use App\Models\Media\MessageSend;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/** What the people who got a message can open without logging in: the picture, the PDF, unsubscribing. Signed links only. */
class MessagePublicController extends Controller
{
    public function image(Message $message)
    {
        abort_unless($message->image_path && Storage::disk('local')->exists($message->image_path), 404);

        return response()->file(Storage::disk('local')->path($message->image_path), ['Cache-Control' => 'public, max-age=2592000']);
    }

    public function pdf(Message $message)
    {
        abort_unless($message->pdf_path && Storage::disk('local')->exists($message->pdf_path), 404);

        return response()->file(Storage::disk('local')->path($message->pdf_path), ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.addslashes($message->pdf_name ?: 'CLUK.pdf').'"']);
    }

    public function unsubscribe(Request $request, string $list, MessageSend $send): View
    {
        app()->setLocale($send->locale);
        if ($request->isMethod('post')) {
            $field = $list === 'newsletter' ? 'newsletter' : 'promo_opt_out';
            $value = $list !== 'newsletter';
            foreach (array_filter([$send->contact, $send->member]) as $who) {
                $who->forceFill([$field => $value] + ($field === 'newsletter' && $who instanceof Contact ? ['newsletter_changed_at' => now()] : []))->save();
                AuditLog::record('messages.unsubscribed', $who, ['list' => $list, 'email' => $send->email]);
            }

            return view('media.messages.unsubscribed', ['list' => $list, 'done' => true, 'send' => $send]);
        }

        return view('media.messages.unsubscribed', ['list' => $list, 'done' => false, 'send' => $send]);
    }
}
