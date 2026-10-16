<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->comment('Kullanıcılar');
            $table->string('email')->nullable()->change();
            $table->string('username', 50)->nullable();
            $table->boolean('active')->default(true);
            $table->foreignUuid('site_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('shift', 10)->nullable();
            $table->softDeletes();
        });

        SchemaRules::uniqueActive('users', ['username'], 'username IS NOT NULL');
        SchemaRules::checkIn('users', 'shift', ['day', 'night'], nullable: true);
        SchemaRules::check('users', 'login', 'email IS NOT NULL OR username IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_username_active_unique');
            $table->dropConstrainedForeignId('site_id');
            $table->dropColumn(['username', 'active', 'shift', 'deleted_at']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
