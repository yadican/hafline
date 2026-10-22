<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\VehicleSiteMonthlyReportRequest;
use App\Services\Reports\VehicleSiteMonthlyReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleSiteMonthlyReportController extends AdminController
{
    protected ?string $permissionAbility = 'view-report-vehicle-site-monthly';

    public function __construct(private VehicleSiteMonthlyReportService $reports) {}

    public function index(VehicleSiteMonthlyReportRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.reports.vehicle-site-monthly.index', [
            'title' => 'Plaka şantiye döküm',
            'options' => $this->reports->formOptions(),
            'filters' => $request->displayFilters(),
            'report' => $filters === null ? null : $this->reports->report($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(VehicleSiteMonthlyReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        $response = $this->reports->exportCsv($filters);

        abort_if($response === null, 404);

        return $response;
    }
}
