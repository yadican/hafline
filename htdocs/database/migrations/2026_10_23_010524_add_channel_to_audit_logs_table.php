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
    private array $actions = [
        'created',
        'updated',
        'deleted',
        'restored',
        'login',
        'logout',
        'submitted',
        'viewed',
    ];

    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('channel', 10)->default('web')->after('user_id');
            $table->index(['channel', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        if (SchemaRules::isPostgres()) {
            DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check');
            DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_channel_check');
        }

        SchemaRules::checkIn('audit_logs', 'action', $this->actions);
        SchemaRules::checkIn('audit_logs', 'channel', ['web', 'api']);
    }

    public function down(): void
    {
        if (SchemaRules::isPostgres()) {
            DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check');
            DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_channel_check');
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['channel', 'created_at']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropColumn('channel');
        });

        SchemaRules::checkIn('audit_logs', 'action', [
            'created',
            'updated',
            'deleted',
            'restored',
            'login',
            'logout',
            'submitted',
        ]);
    }
};
