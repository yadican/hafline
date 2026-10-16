<?php

use App\Support\Database\SchemaRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->comment('Çalışanlar');
            $table->uuid('id')->primary();
            $table->foreignUuid('party_id')->constrained()->restrictOnDelete();
            $table->date('started_on')->nullable();
            $table->string('vehicle_info')->nullable();
            $table->string('occupation')->nullable();
            $table->decimal('salary', 14, 2)->default(0);
            $table->string('bank_account_no', 100)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        SchemaRules::uniqueActive('employees', ['party_id']);

        Schema::create('employee_periods', function (Blueprint $table) {
            $table->comment('Çalışan Dönemleri');
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained()->restrictOnDelete();
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'started_on']);
        });

        SchemaRules::check('employee_periods', 'dates', 'ended_on IS NULL OR started_on IS NULL OR ended_on >= started_on');

        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->comment('Çalışan Maaşları');
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->index()->constrained()->restrictOnDelete();
            $table->date('period');
            $table->decimal('base_salary', 14, 2)->default(0);
            $table->unsignedSmallInteger('worked_days')->default(0);
            $table->decimal('earned_salary', 14, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('period');
        });

        SchemaRules::uniqueActive('employee_salaries', ['employee_id', 'period']);

        Schema::create('employee_payments', function (Blueprint $table) {
            $table->comment('Çalışan Ödemeleri');
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained()->restrictOnDelete();
            $table->string('payment_method', 20)->nullable()->index();
            $table->decimal('amount', 14, 2);
            $table->date('period');
            $table->date('occurred_on');
            $table->string('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'occurred_on']);
            $table->index(['employee_id', 'period']);
        });

        SchemaRules::check('employee_payments', 'amount', 'amount > 0');
        SchemaRules::checkPaymentMethod('employee_payments');

        Schema::create('employee_bonuses', function (Blueprint $table) {
            $table->comment('Çalışan Prim Ödülleri');
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('occurred_on');
            $table->string('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'occurred_on']);
        });

        SchemaRules::check('employee_bonuses', 'amount', 'amount > 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_bonuses');
        Schema::dropIfExists('employee_payments');
        Schema::dropIfExists('employee_salaries');
        Schema::dropIfExists('employee_periods');
        Schema::dropIfExists('employees');
    }
};
