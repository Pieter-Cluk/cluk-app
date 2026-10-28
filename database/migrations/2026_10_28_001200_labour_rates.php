<?php

use App\Models\AuditLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** The labour rates Pieter gave (28/10/2026), per hour excl. VAT. Only set where no rate was entered yet. */
    private const RATES = ['Installation / mounting' => 5500, 'Programming / commissioning' => 6000, 'Technician / operator' => 7500, 'Stagehand / handling' => 4500];

    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return; // tests start from types without a rate
        }
        $set = [];
        foreach (DB::table('labour_types')->whereNull('price_cents')->get() as $t) {
            $en = json_decode((string) $t->names, true)['en'] ?? null;
            if (isset(self::RATES[$en])) {
                DB::table('labour_types')->where('id', $t->id)->update(['price_cents' => self::RATES[$en], 'unit' => 'hour', 'updated_at' => now()]);
                $set[] = $en.': '.number_format(self::RATES[$en] / 100, 2).'/hour';
            }
        }
        if ($set) {
            AuditLog::record('labour.rates_changed', null, ['changes' => implode(' · ', $set)]);
        }
    }

    public function down(): void {}
};
