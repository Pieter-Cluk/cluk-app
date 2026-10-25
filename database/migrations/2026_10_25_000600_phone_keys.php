<?php

use App\Support\Phone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Phone numbers in one international format, plus a digits-only key to find a caller (Linkus call pop-up). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->string('phone_key', 20)->nullable()->index();
        });
        Schema::table('contacts', function (Blueprint $t) {
            $t->string('phone_key', 20)->nullable()->index();
            $t->string('mobile_key', 20)->nullable()->index();
        });

        DB::table('companies')->whereNotNull('phone')->where('phone', '!=', '')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $r) {
                DB::table('companies')->where('id', $r->id)->update(['phone' => Phone::international($r->phone, $r->country), 'phone_key' => Phone::key($r->phone, $r->country)]);
            }
        });
        DB::table('contacts')->where(fn ($q) => $q->whereNotNull('phone')->orWhereNotNull('mobile'))->orderBy('id')->chunkById(500, function ($rows) {
            $countries = DB::table('company_contact')->join('companies', 'companies.id', '=', 'company_contact.company_id')
                ->whereIn('company_contact.contact_id', $rows->pluck('id'))->orderByDesc('company_contact.is_main')
                ->get(['company_contact.contact_id', 'companies.country'])->unique('contact_id')->pluck('country', 'contact_id');
            foreach ($rows as $r) {
                $c = $countries[$r->id] ?? 'BE';
                DB::table('contacts')->where('id', $r->id)->update([
                    'phone' => Phone::international($r->phone, $c), 'phone_key' => Phone::key($r->phone, $c),
                    'mobile' => Phone::international($r->mobile, $c), 'mobile_key' => Phone::key($r->mobile, $c),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('phone_key'));
        Schema::table('contacts', fn (Blueprint $t) => $t->dropColumn(['phone_key', 'mobile_key']));
    }
};
