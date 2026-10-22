<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\SiteExitReportRequest;
use App\Services\Reports\SiteExitReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SiteExitReportController extends AdminController
{
    protected ?string $permissionAbility = 'view-report-site-exits';

    public function __construct(private SiteExitReportService $reports) {}

    public function index(SiteExitReportRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.reports.site-exits.index', [
            'title' => 'Şantiye çıkış raporları',
            'options' => $this->reports->formOptions(),
            'filters' => $request->displayFilters(),
            'matrix' => $filters === null ? null : $this->reports->matrix($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(SiteExitReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        $response = $this->reports->exportCsv($filters);

        abort_if($response === null, 404);

        return $response;
    }
}
