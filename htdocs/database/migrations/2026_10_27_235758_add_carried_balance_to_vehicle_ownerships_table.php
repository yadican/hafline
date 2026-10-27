<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A transfer closes the previous owner's balance on the vehicle: carried_balance is what the
     * company owed that owner on the plate at the transfer (negative: the owner owed the company),
     * moved to the new owner. Zero means it was settled with the previous owner first.
     */
    public function up(): void
    {
        Schema::table('vehicle_ownerships', function (Blueprint $table) {
            $table->foreignUuid('carried_from_party_id')->nullable()->after('ended_on')->constrained('parties')->restrictOnDelete();
            $table->decimal('carried_balance', 14, 2)->nullable()->after('carried_from_party_id');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_ownerships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carried_from_party_id');
            $table->dropColumn('carried_balance');
        });
    }
};
