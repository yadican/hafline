<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\OwnerPeriodicReportRequest;
use App\Services\Reports\OwnerPeriodicReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnerPeriodicReportController extends AdminController
{
    public function __construct(private OwnerPeriodicReportService $reports) {}

    public function index(OwnerPeriodicReportRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.reports.owner-periodic.index', [
            'title' => 'Plaka sahibi dönemsel',
            'options' => $this->reports->formOptions(),
            'filters' => $request->displayFilters(),
            'report' => $filters === null ? null : $this->reports->report($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(OwnerPeriodicReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        return $this->reports->exportCsv($filters);
    }
}
