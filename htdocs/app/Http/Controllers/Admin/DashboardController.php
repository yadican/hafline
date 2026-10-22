<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Services\DashboardService;
use Illuminate\View\View;

class DashboardController extends AdminController
{
    protected ?string $permissionAbility = 'view-dashboard';

    public function __construct(private DashboardService $dashboard) {}

    public function index(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $summary = $this->dashboard->summary($user);

        return view('admin.dashboard', [
            'title' => 'Gösterge paneli',
            'stats' => $summary['stats'],
            'shortcuts' => $summary['shortcuts'],
            'recentHauls' => $summary['recent_hauls'],
            'cashSummary' => $summary['cash_summary'],
            'todayLabel' => today()->translatedFormat('d F Y'),
        ]);
    }
}
