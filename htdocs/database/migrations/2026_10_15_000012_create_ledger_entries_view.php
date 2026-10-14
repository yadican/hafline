<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Perspective is the counterparty's account: debit = the party owes us / we paid it,
 * credit = we owe the party. Balance = SUM(debit) - SUM(credit).
 * See docs/legacy/reports.md for which sources each report reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sources = [
            $this->source('hauls', 'vehicle', 'vehicle_id', 'date(hauled_at)', '0', 'price', 'ticket_no'),
            $this->source('hauls', 'dump_site', 'dump_site_id', 'date(hauled_at)', '0', 'dump_price', 'ticket_no', 'dump_price > 0'),
            $this->source('fuel_receipts', 'vehicle', 'vehicle_id', 'issued_on', 'amount', '0', 'ticket_no'),
            $this->source('vehicle_payments', 'vehicle', 'vehicle_id', 'occurred_on', 'amount', '0', 'description', paymentType: true),
            $this->source('vehicle_receivables', 'vehicle', 'vehicle_id', 'occurred_on', '0', 'amount', 'description', paymentType: true),
            $this->source('vehicle_fines', 'vehicle', 'vehicle_id', 'paid_on', 'amount', '0', 'description', "status = 'paid'", paymentType: true),
            $this->source('vehicle_owner_payments', 'vehicle_owner', 'vehicle_owner_id', 'occurred_on', 'amount', '0', 'description', paymentType: true),
            $this->source('dump_site_payments', 'dump_site', 'dump_site_id', 'occurred_on', 'amount', '0', 'description', paymentType: true),
            $this->source('site_transactions', 'site', 'site_id', 'occurred_on', 'debit', 'credit', 'description', paymentType: true),
            $this->source('company_transactions', 'company', 'company_id', 'occurred_on', 'debit', 'credit', 'description', paymentType: true, role: 'role'),
            $this->source('employee_salaries', 'employee', 'employee_id', 'period', '0', 'earned_salary', null),
            $this->source('employee_bonuses', 'employee', 'employee_id', 'occurred_on', '0', 'amount', 'description'),
            $this->source('employee_payments', 'employee', 'employee_id', 'occurred_on', 'amount', '0', 'description', paymentType: true),
            $this->source('cash_entries', 'cash', null, 'occurred_on', 'debit', 'credit', 'description'),
            $this->source('other_payments', 'other', null, 'occurred_on', 'amount', '0', 'description', paymentType: true),
        ];

        DB::statement('CREATE VIEW ledger_entries AS '.implode(' UNION ALL ', $sources));
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS ledger_entries');
    }

    private function source(
        string $table,
        string $partyType,
        ?string $partyColumn,
        string $date,
        string $debit,
        string $credit,
        ?string $description,
        ?string $where = null,
        bool $paymentType = false,
        ?string $role = null,
    ): string {
        $columns = [
            "CAST('{$table}' AS varchar(40)) AS source_table",
            'id AS source_id',
            "CAST('{$partyType}' AS varchar(20)) AS party_type",
            ($partyColumn ?? 'CAST(NULL AS uuid)').' AS party_id',
            ($role ?? 'CAST(NULL AS varchar(20))').' AS party_role',
            "{$date} AS occurred_on",
            "CAST({$debit} AS numeric(14, 2)) AS debit",
            "CAST({$credit} AS numeric(14, 2)) AS credit",
            ($paymentType ? 'payment_type_id' : 'CAST(NULL AS uuid)').' AS payment_type_id',
            ($description ?? 'CAST(NULL AS varchar(255))').' AS description',
            'created_at',
        ];

        return sprintf(
            'SELECT %s FROM %s WHERE deleted_at IS NULL%s',
            implode(', ', $columns),
            $table,
            $where ? " AND {$where}" : '',
        );
    }
};
