<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\DumpSiteReportRequest;
use App\Services\Reports\DumpSiteReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DumpSiteReportController extends AdminController
{
    public function __construct(private DumpSiteReportService $reports) {}

    public function index(DumpSiteReportRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.reports.dump-sites.index', [
            'title' => 'Döküm yeri raporları',
            'options' => $this->reports->formOptions(),
            'filters' => $request->displayFilters(),
            'matrix' => $filters === null ? null : $this->reports->matrix($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(DumpSiteReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        $response = $this->reports->exportCsv($filters);

        abort_if($response === null, 404);

        return $response;
    }
}
