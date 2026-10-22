<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\SiteCariReportRequest;
use App\Services\Reports\SiteCariReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SiteCariReportController extends AdminController
{
    public function __construct(private SiteCariReportService $reports) {}

    public function index(SiteCariReportRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.reports.site-cari.index', [
            'title' => 'Şantiye cari',
            'options' => $this->reports->formOptions(),
            'filters' => $request->displayFilters(),
            'report' => $filters === null ? null : $this->reports->report($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(SiteCariReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        return $this->reports->exportCsv($filters);
    }
}
