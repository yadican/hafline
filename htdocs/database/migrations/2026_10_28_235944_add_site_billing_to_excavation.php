<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A site gets its own cari (a company with the site role) that owes us for the work and pays us.
 * Billing is per trip (each haul debits the site at the route's site price) or by progress bill.
 * Documents keep the site cari they posted to, so a later change on the site card does not rewrite them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->foreignUuid('party_id')->nullable()->after('name')->index()->constrained()->restrictOnDelete();
            $table->string('billing_mode', 10)->default('progress')->after('party_id');
        });

        SchemaRules::checkIn('sites', 'billing_mode', ['per_trip', 'progress']);

        Schema::create('route_site_prices', function (Blueprint $table) {
            $table->comment('Rota - Araç Sınıfı Şantiye Satış Fiyatları');
            $table->uuid('id')->primary();
            $table->foreignUuid('route_id')->index()->constrained('haul_routes')->cascadeOnDelete();
            $table->foreignUuid('vehicle_class_id')->index()->constrained()->restrictOnDelete();
            $table->decimal('price', 12, 2);
            $table->timestamps();
            $table->softDeletes();
        });

        SchemaRules::uniqueActive('route_site_prices', ['route_id', 'vehicle_class_id']);

        Schema::table('hauls', function (Blueprint $table) {
            $table->decimal('site_price', 12, 2)->default(0)->after('dump_price');
            $table->foreignUuid('site_party_id')->nullable()->after('site_price')->index()->constrained('parties')->restrictOnDelete();
        });

        Schema::create('site_bills', function (Blueprint $table) {
            $table->comment('Şantiye Hakedişleri');
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('party_id')->index()->constrained()->restrictOnDelete();
            $table->date('occurred_on');
            $table->decimal('amount', 14, 2);
            $table->string('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['site_id', 'occurred_on']);
        });

        SchemaRules::check('site_bills', 'amount', 'amount > 0');

        Schema::table('site_transactions', function (Blueprint $table) {
            $table->foreignUuid('party_id')->nullable()->after('site_id')->index()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('site_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('party_id');
        });

        Schema::dropIfExists('site_bills');

        Schema::table('hauls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_party_id');
            $table->dropColumn('site_price');
        });

        Schema::dropIfExists('route_site_prices');

        if (SchemaRules::isPostgres()) {
            DB::statement('ALTER TABLE sites DROP CONSTRAINT IF EXISTS sites_billing_mode_check');
        }

        Schema::table('sites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('party_id');
            $table->dropColumn('billing_mode');
        });
    }
};
