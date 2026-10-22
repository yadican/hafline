<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\VehicleBalanceReportRequest;
use App\Services\Reports\VehicleBalanceReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleBalanceReportController extends AdminController
{
    protected ?string $permissionAbility = 'view-report-vehicle-balance';

    public function __construct(private VehicleBalanceReportService $reports) {}

    public function index(VehicleBalanceReportRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.reports.vehicle-balance.index', [
            'title' => 'Plaka bakiye',
            'options' => $this->reports->formOptions(),
            'filters' => $request->displayFilters(),
            'report' => $filters === null ? null : $this->reports->report($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(VehicleBalanceReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        return $this->reports->exportCsv($filters);
    }
}
