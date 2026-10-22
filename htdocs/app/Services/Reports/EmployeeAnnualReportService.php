<?php

namespace App\Services\Reports;

use App\Repositories\Reports\EmployeeAnnualReportRepository;
use App\Services\EmployeeService;
use App\Services\Service;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeAnnualReportService extends Service
{
    public function __construct(
        private EmployeeAnnualReportRepository $reports,
        private EmployeeService $employees,
    ) {}

    /**
     * @return array{employees: array<string, string>, years: array<int, string>}
     */
    public function formOptions(?string $includeEmployee = null): array
    {
        $year = (int) now()->year;
        $years = [];

        for ($y = $year + 1; $y >= $year - 9; $y--) {
            $years[$y] = (string) $y;
        }

        return [
            'employees' => $this->employees->options($includeEmployee),
            'years' => $years,
        ];
    }

    /**
     * @param  array{employee_id: string, year: int}  $filters
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     employee: array{name: string, started_on: ?string, address: ?string, phone: ?string, mobile: ?string, occupation: ?string},
     *     year: int,
     *     opening: float,
     *     lines: list<array{date: ?string, period: string, description: string, salary: float, payment: float, balance: float}>,
     *     totals: array{salary: float, bonus: float, payment: float, closing: float}
     * }|null
     */
    public function report(array $filters): ?array
    {
        $employee = $this->reports->employee($filters['employee_id']);

        if ($employee === null) {
            return null;
        }

        $year = $filters['year'];
        $opening = $this->reports->openingCarry($employee->id, $year);
        $running = $opening;
        $lines = [];
        $totalSalary = 0.0;
        $totalBonus = 0.0;
        $totalPayment = 0.0;

        $lines[] = [
            'date' => null,
            'period' => '',
            'description' => 'Geçmiş Yıllar Devir',
            'salary' => 0.0,
            'payment' => 0.0,
            'balance' => $running,
        ];

        $events = [];

        foreach ($this->reports->salariesInYear($employee->id, $year) as $salary) {
            $events[] = [
                'sort' => $salary->period->format('Y-m-d').'-1-'.$salary->id,
                'date' => $salary->period->toDateString(),
                'period' => $salary->period->format('Y-m'),
                'description' => SiteExitReportService::MONTHS[(int) $salary->period->format('n')].' '.$year.' Maaşı',
                'salary' => (float) $salary->earned_salary,
                'payment' => 0.0,
                'kind' => 'salary',
            ];
        }

        foreach ($this->reports->bonusesInYear($employee->id, $year) as $bonus) {
            $events[] = [
                'sort' => $bonus->occurred_on->format('Y-m-d').'-2-'.$bonus->id,
                'date' => $bonus->occurred_on->toDateString(),
                'period' => $bonus->occurred_on->format('Y-m'),
                'description' => $bonus->description ?: 'Prim',
                'salary' => (float) $bonus->amount,
                'payment' => 0.0,
                'kind' => 'bonus',
            ];
        }

        foreach ($this->reports->paymentsInYear($employee->id, $year) as $payment) {
            $events[] = [
                'sort' => $payment->occurred_on->format('Y-m-d').'-3-'.$payment->id,
                'date' => $payment->occurred_on->toDateString(),
                'period' => $payment->period->format('Y-m'),
                'description' => $payment->description ?: 'Ödeme',
                'salary' => 0.0,
                'payment' => (float) $payment->amount,
                'kind' => 'payment',
            ];
        }

        usort($events, fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        foreach ($events as $event) {
            $running = round($running + $event['salary'] - $event['payment'], 2);

            if ($event['kind'] === 'salary') {
                $totalSalary += $event['salary'];
            } elseif ($event['kind'] === 'bonus') {
                $totalBonus += $event['salary'];
            } else {
                $totalPayment += $event['payment'];
            }

            $lines[] = [
                'date' => $event['date'],
                'period' => $event['period'],
                'description' => $event['description'],
                'salary' => $event['salary'],
                'payment' => $event['payment'],
                'balance' => $running,
            ];
        }

        return [
            'title' => 'Personel Yıllık Ödeme Raporu',
            'subtitle' => $employee->name.' · '.$year,
            'employee' => [
                'name' => $employee->name,
                'started_on' => $employee->started_on?->toDateString(),
                'address' => $employee->party?->address,
                'phone' => $employee->party?->phone,
                'mobile' => $employee->party?->mobile,
                'occupation' => $employee->occupation?->label(),
            ],
            'year' => $year,
            'opening' => $opening,
            'lines' => $lines,
            'totals' => [
                'salary' => round($totalSalary, 2),
                'bonus' => round($totalBonus, 2),
                'payment' => round($totalPayment, 2),
                'closing' => $running,
            ],
        ];
    }

    /**
     * @param  array{employee_id: string, year: int}  $filters
     */
    public function exportCsv(array $filters): StreamedResponse
    {
        $report = $this->report($filters);

        abort_if($report === null, 404);

        $filename = sprintf('personel-yillik-%s-%04d.csv', $filters['employee_id'], $report['year']);

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Tarih', 'Dönem', 'Açıklama', 'Maaş / Prim', 'Ödeme', 'Devir'], ';');

            foreach ($report['lines'] as $line) {
                fputcsv($handle, [
                    $line['date'] ? Carbon::parse($line['date'])->format('d.m.Y') : '',
                    $line['period'],
                    $line['description'],
                    $line['salary'] > 0 ? number_format($line['salary'], 2, ',', '.') : '',
                    $line['payment'] > 0 ? number_format($line['payment'], 2, ',', '.') : '',
                    number_format($line['balance'], 2, ',', '.'),
                ], ';');
            }

            fputcsv($handle, [
                '',
                '',
                'Toplam',
                number_format($report['totals']['salary'] + $report['totals']['bonus'], 2, ',', '.'),
                number_format($report['totals']['payment'], 2, ',', '.'),
                number_format($report['totals']['closing'], 2, ',', '.'),
            ], ';');

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
