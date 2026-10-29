<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** The first mails read from requests@ were saved in UTC: show them in Brussels time (+1 h in winter). */
    public function up(): void
    {
        foreach (DB::table('incoming_messages')->where('channel', 'email')->where('payload', 'like', '%"mail_id"%')->get(['id', 'received_at', 'created_at']) as $m) {
            if ($m->received_at && strtotime($m->created_at) - strtotime($m->received_at) > 3000) {
                DB::table('incoming_messages')->where('id', $m->id)->update(['received_at' => date('Y-m-d H:i:s', strtotime($m->received_at) + 3600)]);
            }
        }
    }

    public function down(): void {}
};
