<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $actions = ['created', 'updated', 'deleted', 'restored', 'login', 'logout', 'submitted'];

    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->comment('Sistem Günlükleri');
            $table->string('method', 10)->nullable();
            $table->string('route')->nullable();
            $table->text('url')->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedSmallInteger('status')->nullable();
            $table->json('payload')->nullable();
            $table->json('context')->nullable();
        });

        if (SchemaRules::isPostgres()) {
            DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check');
        }

        SchemaRules::checkIn('audit_logs', 'action', $this->actions);
    }

    public function down(): void
    {
        if (SchemaRules::isPostgres()) {
            DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check');
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['method', 'route', 'url', 'user_agent', 'status', 'payload', 'context']);
        });

        SchemaRules::checkIn('audit_logs', 'action', ['created', 'updated', 'deleted', 'restored']);
    }
};
