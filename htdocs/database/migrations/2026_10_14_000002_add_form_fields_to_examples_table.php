<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('examples', function (Blueprint $table) {
            $table->comment('Örnek Form Alanları');
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('website')->nullable();
            $table->string('secret')->nullable();
            $table->string('reference')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->date('event_date')->nullable();
            $table->time('event_time')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->string('event_month', 7)->nullable();
            $table->string('event_week', 8)->nullable();
            $table->string('color', 7)->nullable();
            $table->unsignedTinyInteger('level')->nullable();
            $table->string('status', 20)->nullable();
            $table->string('shift', 20)->nullable();
            $table->json('tags')->nullable();
            $table->json('channels')->nullable();
            $table->boolean('active')->default(false);
            $table->string('attachment')->nullable();
            $table->string('source', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('examples', function (Blueprint $table) {
            $table->dropColumn([
                'email',
                'phone',
                'website',
                'secret',
                'reference',
                'quantity',
                'amount',
                'event_date',
                'event_time',
                'starts_at',
                'event_month',
                'event_week',
                'color',
                'level',
                'status',
                'shift',
                'tags',
                'channels',
                'active',
                'attachment',
                'source',
            ]);
        });
    }
};
