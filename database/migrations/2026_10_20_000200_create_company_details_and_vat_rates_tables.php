<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CLUK's own details, printed on every document. Always one row.
        Schema::create('company_details', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name', 120);
            $table->string('legal_form', 20)->nullable();
            $table->string('vat_number', 20)->nullable();
            $table->string('rpr', 120)->nullable();
            $table->string('street', 160)->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('city', 80)->nullable();
            $table->char('country', 2)->default('BE');
            $table->string('warehouse_address', 255)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('invoice_email', 190)->nullable();
            $table->string('website', 190)->nullable();
            $table->unsignedSmallInteger('payment_term_days')->default(30);
            $table->unsignedSmallInteger('quote_valid_days')->default(14);
            $table->unsignedSmallInteger('option_days')->default(3);
            $table->string('footer_line', 255)->nullable();
            // Default VAT per kind of customer, as OkiOki codes (see vat_rates.code).
            $table->string('vat_default_be', 4)->default('21');
            $table->string('vat_default_eu_business', 4)->default('46');
            $table->string('vat_default_eu_private', 4)->default('21');
            $table->string('vat_default_outside_eu', 4)->default('47');
            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank', 80)->nullable();
            $table->string('iban', 40);
            $table->string('bic', 11)->nullable();
            $table->boolean('is_main')->default(false);
            $table->boolean('on_documents')->default(true);
            $table->timestamps();
        });

        // VAT rates with their validity, so a government change can be planned
        // ahead and issued documents keep their rate. Rate in hundredths of a
        // percent (2100 = 21%). The code is the one OkiOki expects.
        Schema::create('vat_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->unsignedSmallInteger('rate_bp');
            $table->string('code', 4);
            $table->string('zone', 12); // be, eu, outside
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->timestamps();
            $table->index(['code', 'valid_from']);
        });

        $now = now();
        DB::table('company_details')->insert([
            'legal_name' => 'CLUK', 'legal_form' => 'BV', 'vat_number' => 'BE1010948054',
            'street' => 'Karreweg 17B', 'postcode' => '9870', 'city' => 'Zulte', 'country' => 'BE',
            'warehouse_address' => 'Karreweg 17B, 9870 Zulte', 'website' => 'cluk.live',
            'footer_line' => 'Cluk it - Why Not!', 'created_at' => $now, 'updated_at' => $now,
        ]);

        foreach ([
            ['21% standard rate', 2100, '21', 'be'],
            ['12% reduced rate', 1200, '12', 'be'],
            ['6% reduced rate', 600, '06', 'be'],
            ['0% · EU business (46)', 0, '46', 'eu'],
            ['0% · outside the EU (47)', 0, '47', 'outside'],
        ] as [$name, $bp, $code, $zone]) {
            DB::table('vat_rates')->insert([
                'name' => $name, 'rate_bp' => $bp, 'code' => $code, 'zone' => $zone,
                'valid_from' => '1996-01-01', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_rates');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('company_details');
    }
};
