<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** MariaDB (production) refuses index names longer than 64 characters; SQLite does not. */
class SchemaNamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_names_fit_mariadb(): void
    {
        $long = collect(DB::select("select name from sqlite_master where type = 'index' and name not like 'sqlite_%'"))
            ->pluck('name')->filter(fn ($n) => strlen($n) > 64)->values()->all();
        $this->assertSame([], $long);
        // Foreign keys get table_column_foreign: check those too.
        foreach (DB::select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%'") as $t) {
            foreach (DB::select('pragma foreign_key_list('.$t->name.')') as $fk) {
                $this->assertLessThanOrEqual(64, strlen($t->name.'_'.$fk->from.'_foreign'), $t->name.'.'.$fk->from);
            }
        }
    }
}
