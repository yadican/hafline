<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->comment('Kullanıcı Notları');
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->text('body');
            $table->date('noted_on');
            $table->timestamps();
            $table->softDeletes();

            $table->index('noted_on');
        });

        // Append-only: no updated_at / soft deletes.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->comment('Sistem Günlükleri');
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('action', 10);
            $table->nullableUuidMorphs('auditable');
            $table->string('ip_address', 45)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });

        SchemaRules::checkIn('audit_logs', 'action', ['created', 'updated', 'deleted', 'restored']);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notes');
    }
};
