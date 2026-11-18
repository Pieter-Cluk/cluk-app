<?php

use App\Models\AuditLog;
use App\Models\ProductSales\SalesQuote;
use App\Models\Sales\Deal;
use App\Models\Sales\Quote;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    // Deals already lost or disqualified: their open quotes are lost too (no mails), so they leave the tasks.
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        foreach (Deal::whereIn('stage', ['lost', 'disqualified'])->get() as $deal) {
            $note = 'Deal '.($deal->stage === 'lost' ? 'lost' : 'disqualified').($deal->lost_reason ? ': '.$deal->lost_reason : '');
            foreach (Quote::where('deal_id', $deal->id)->whereIn('status', ['draft', 'sent', 'change'])->get() as $q) {
                $q->forceFill(['status' => 'declined', 'answered_at' => now(), 'decline_reason' => in_array($deal->lost_reason, ['price', 'competitor', 'cancelled'], true) ? $deal->lost_reason : 'other', 'answer_note' => $note, 'reserved_until' => null])->save();
                AuditLog::record('quote.declined', $q, ['reason' => 'deal closed earlier', 'migration' => true]);
            }
            foreach (SalesQuote::where('deal_id', $deal->id)->whereIn('status', ['draft', 'sent'])->get() as $q) {
                $q->update(['status' => 'declined', 'answered_at' => now(), 'decline_reason' => $note]);
                AuditLog::record('psales.declined', $q, ['reason' => 'deal closed earlier', 'migration' => true]);
            }
        }
    }

    public function down(): void {}
};
