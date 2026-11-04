<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Facebook, Instagram and YouTube post times were stored in UTC clock time: put them in Brussels time (like LinkedIn). */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        $ids = DB::table('social_accounts')->whereIn('platform', ['facebook', 'instagram', 'google', 'youtube'])->pluck('id');
        DB::table('social_posts')->whereIn('account_id', $ids)->whereNotNull('published_at')->orderBy('id')->each(function ($p) {
            $local = Carbon::parse($p->published_at, 'UTC')->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
            DB::table('social_posts')->where('id', $p->id)->update(['published_at' => $local]);
        });
    }

    public function down(): void {}
};
