<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // Level per module for a role: 0 none, 1 view, 2 edit, 3 manage.
        Schema::create('role_module_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('module', 40);
            $table->unsignedTinyInteger('level')->default(0);
            $table->unique(['role_id', 'module']);
        });

        Schema::create('role_rights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('right', 60);
            $table->unique(['role_id', 'right']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('title')->constrained()->nullOnDelete();
            $table->foreignId('reports_to_id')->nullable()->after('role_id')->constrained('users')->nullOnDelete();
        });

        // A different level for one person on one module.
        Schema::create('user_module_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module', 40);
            $table->unsignedTinyInteger('level');
            $table->unique(['user_id', 'module']);
        });

        // Extra rights for one person, on top of the role.
        Schema::create('user_rights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('right', 60);
            $table->unique(['user_id', 'right']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->nullableMorphs('subject');
            $table->json('data')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('user_rights');
        Schema::dropIfExists('user_module_overrides');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reports_to_id');
            $table->dropConstrainedForeignId('role_id');
        });
        Schema::dropIfExists('role_rights');
        Schema::dropIfExists('role_module_levels');
        Schema::dropIfExists('roles');
    }
};
