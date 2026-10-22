<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\DumpSitePaymentReportRequest;
use App\Services\Reports\DumpSitePaymentReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DumpSitePaymentReportController extends AdminController
{
    protected ?string $permissionAbility = 'view-report-dump-site-payments';

    public function __construct(private DumpSitePaymentReportService $reports) {}

    public function index(DumpSitePaymentReportRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.reports.dump-site-payments.index', [
            'title' => 'Döküm yeri ödeme',
            'options' => $this->reports->formOptions(),
            'filters' => $request->displayFilters(),
            'report' => $filters === null ? null : $this->reports->report($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(DumpSitePaymentReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        return $this->reports->exportCsv($filters);
    }
}
