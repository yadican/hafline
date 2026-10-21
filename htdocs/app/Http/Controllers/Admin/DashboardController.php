<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard) {}

    public function index(): View
    {
        $summary = $this->dashboard->summary();

        return view('admin.dashboard', [
            'title' => 'Gösterge paneli',
            'stats' => $summary['stats'],
            'shortcuts' => $summary['shortcuts'],
        ]);
    }
}
