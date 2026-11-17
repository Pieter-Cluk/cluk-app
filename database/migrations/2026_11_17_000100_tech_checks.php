<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check Builder: a step-by-step procedure, published in versions; items point to one.
        Schema::create('check_procedures', function (Blueprint $t) {
            $t->id();
            $t->json('names');                          // {nl, en}
            $t->json('draft_steps')->nullable();         // what is being edited; published as a version
            $t->unsignedBigInteger('current_version_id')->nullable();
            $t->boolean('active')->default(true);
            $t->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('check_procedure_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('procedure_id')->constrained('check_procedures')->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->json('steps');
            $t->string('note', 200)->nullable();
            $t->foreignId('published_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['procedure_id', 'version']);
        });
        Schema::table('equipment_items', function (Blueprint $t) {
            $t->foreignId('check_procedure_id')->nullable()->constrained('check_procedures')->nullOnDelete();
        });
        // Tech Check: the version used, the step reached, the answers per step.
        Schema::table('warehouse_checks', function (Blueprint $t) {
            $t->foreignId('procedure_version_id')->nullable()->constrained('check_procedure_versions')->nullOnDelete();
            $t->unsignedSmallInteger('step')->default(0);
            $t->json('answers')->nullable();
        });
        // Return costs: the account owner looks first, then the customer is informed; invoiced after the deadline.
        Schema::create('warehouse_charge_notices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $t->string('status', 10)->default('review');  // review | sent | objected | invoiced | waived
            $t->string('token', 48)->nullable()->unique();
            $t->string('sent_to', 190)->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->foreignId('sent_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->date('deadline')->nullable();
            $t->text('objection')->nullable();
            $t->timestamp('objected_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_charge_notices');
        Schema::table('warehouse_checks', function (Blueprint $t) {
            $t->dropConstrainedForeignId('procedure_version_id');
            $t->dropColumn(['step', 'answers']);
        });
        Schema::table('equipment_items', fn (Blueprint $t) => $t->dropConstrainedForeignId('check_procedure_id'));
        Schema::dropIfExists('check_procedure_versions');
        Schema::dropIfExists('check_procedures');
    }
};
