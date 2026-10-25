<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meeting notes (internal or with a customer): the text from the recording app is
 * pasted in, the app proposes the tasks, and after a check they become tasks of
 * the people who have to do them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $t) {
            $t->id();
            $t->string('title', 200);
            $t->date('held_on');
            $t->string('kind', 12)->default('internal'); // internal, customer
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->json('attendees')->nullable();             // user ids
            $t->longText('notes');
            $t->text('summary')->nullable();
            $t->json('decisions')->nullable();
            $t->json('proposals')->nullable();             // proposed tasks, waiting for the check
            $t->string('analysed_by', 12)->nullable();     // claude, rules
            $t->timestamp('analysed_at')->nullable();
            $t->timestamps();
            $t->index(['held_on']);
        });
        Schema::create('todos', function (Blueprint $t) {
            $t->id();
            $t->string('title', 250);
            $t->text('details')->nullable();
            $t->foreignId('assignee_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->date('due_on')->nullable();
            $t->string('status', 8)->default('open'); // open, done
            $t->timestamp('done_at')->nullable();
            $t->foreignId('done_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('meeting_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->index(['assignee_id', 'status', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todos');
        Schema::dropIfExists('meetings');
    }
};
