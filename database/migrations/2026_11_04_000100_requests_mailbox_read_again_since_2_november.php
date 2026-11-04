<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/** Mails from platforms with a no-reply sender were skipped: read the mailbox again from 2 November (nothing twice). */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        // Read now (the cache is cleared after migrations): the items stay, and nothing comes in twice.
        Cache::forever('requests_mailbox.since', '2026-11-02T00:00:00+01:00');
        Cache::forget('requests_mailbox.pause');
        try {
            \App\Services\Mail\RequestsMailbox::scan();
        } catch (\Throwable) {
            // The 5-minute run reads the last day anyway.
        }
    }

    public function down(): void {}
};
