<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Help: the import article now also explains the export (only if no admin changed it yet). */
return new class extends Migration
{
    public function up(): void
    {
        $a = collect(require database_path('data/help_articles.php'))->firstWhere('slug', 'importing-a-spreadsheet');
        if ($a) {
            DB::table('help_articles')->where('slug', 'importing-a-spreadsheet')->whereNull('updated_by_id')->update([
                'title' => json_encode($a['title'], JSON_UNESCAPED_UNICODE), 'body' => json_encode($a['body'], JSON_UNESCAPED_UNICODE),
                'keywords' => $a['keywords'], 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Text only; nothing to undo.
    }
};
