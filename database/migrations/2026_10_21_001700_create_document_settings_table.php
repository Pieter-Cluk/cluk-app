<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Settings › Documents: a commercial note per document type and the seasonal message.
        Schema::create('document_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();     // note.R-QUO, season
            $table->json('value');
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_settings');
    }
};
