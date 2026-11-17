<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer storage: a customer or supplier keeps equipment at CLUK (Afrit 6 prices: places per day, handling).
        Schema::create('storage_contracts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('status', 8)->default('active');   // active | ended
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->string('note', 300)->nullable();
            $t->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('storage_places', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contract_id')->constrained('storage_contracts')->cascadeOnDelete();
            $t->foreignId('position_id')->constrained('warehouse_positions')->cascadeOnDelete();
            $t->string('kind', 8);                       // shelf | pallet
            $t->date('from');
            $t->date('until')->nullable();
            $t->timestamps();
        });
        // Their equipment: never part of our fleet (no availability, website, wall screens or figures).
        Schema::create('stored_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contract_id')->constrained('storage_contracts')->cascadeOnDelete();
            $t->string('name', 160);
            $t->string('their_number', 60)->nullable();
            $t->foreignId('same_as_item_id')->nullable()->constrained('equipment_items')->nullOnDelete(); // for a quick sub-hire
            $t->foreignId('check_procedure_id')->nullable()->constrained('check_procedures')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('stored_pieces', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stored_item_id')->constrained('stored_items')->cascadeOnDelete();
            $t->string('code', 20)->unique();            // our label: ST-00001
            $t->string('serial_number', 80)->nullable();
            $t->string('position_code', 20)->nullable();
            $t->string('status', 8)->default('in');      // in | out
            $t->timestamps();
        });
        Schema::create('storage_moves', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contract_id')->constrained('storage_contracts')->cascadeOnDelete();
            $t->string('kind', 4);                       // in | out
            $t->date('date');
            $t->unsignedInteger('colis')->default(0);
            $t->unsignedInteger('pallets')->default(0);
            $t->json('pieces')->nullable();              // stored piece ids
            $t->string('signed_name', 120)->nullable();
            $t->string('note', 300)->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        // Tech Check on their pieces: by CLUK (billed: the procedure's duration × the technician rate) or by their staff.
        Schema::create('storage_checks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stored_piece_id')->constrained('stored_pieces')->cascadeOnDelete();
            $t->foreignId('procedure_version_id')->nullable()->constrained('check_procedure_versions')->nullOnDelete();
            $t->unsignedSmallInteger('step')->default(0);
            $t->json('answers')->nullable();
            $t->json('photos')->nullable();
            $t->string('done_by', 10)->default('cluk');  // cluk | customer
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->unsignedSmallInteger('minutes')->nullable(); // billed duration
            $t->string('result', 8)->nullable();             // ok | repair
            $t->foreignId('checked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('storage_statements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contract_id')->constrained('storage_contracts')->cascadeOnDelete();
            $t->date('period_start');
            $t->date('period_end');
            $t->foreignId('pre_invoice_id')->nullable()->constrained('pre_invoices')->nullOnDelete();
            $t->timestamps();
            $t->unique(['contract_id', 'period_start']);
        });
        // The standard duration of a procedure, and the time a check of ours really took.
        Schema::table('check_procedures', fn (Blueprint $t) => $t->unsignedSmallInteger('minutes')->nullable()->after('names'));
        Schema::table('warehouse_checks', function (Blueprint $t) {
            $t->timestamp('started_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_checks', fn (Blueprint $t) => $t->dropColumn('started_at'));
        Schema::table('check_procedures', fn (Blueprint $t) => $t->dropColumn('minutes'));
        foreach (['storage_statements', 'storage_checks', 'storage_moves', 'stored_pieces', 'stored_items', 'storage_places', 'storage_contracts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
