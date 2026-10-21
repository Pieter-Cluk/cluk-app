<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Afrit 6 tasks on the phone: a photo of the goods as proof and a scan of each place.
        Schema::table('afrit6_requests', function (Blueprint $table) {
            $table->json('photos')->nullable()->after('note');
            $table->json('scans')->nullable()->after('photos');      // [{code, at, by}]
        });

        // Goods that arrived without anyone announcing them.
        Schema::create('warehouse_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('photos')->nullable();
            $table->unsignedSmallInteger('colli')->default(0);
            $table->unsignedSmallInteger('pallets')->default(0);
            $table->boolean('damaged')->default(false);
            $table->string('carrier', 80)->nullable();
            $table->string('label_text', 300)->nullable();
            $table->string('match', 10)->nullable();             // member, purchase, return, unknown
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->foreignId('request_id')->nullable()->constrained('afrit6_requests')->nullOnDelete();
            $table->string('status', 10)->default('open');       // open (nobody found yet), done
            $table->foreignId('closed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        $now = now();
        DB::table('warehouse_zones')->insert([
            ['code' => 'A6-24H', 'type' => 'afrit6', 'categories' => null, 'next' => json_encode([]), 'sort' => 200, 'active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table('warehouse_zones')->where('code', 'A6-24H')->delete();
        Schema::dropIfExists('warehouse_deliveries');
        Schema::table('afrit6_requests', fn (Blueprint $t) => $t->dropColumn(['photos', 'scans']));
    }
};
