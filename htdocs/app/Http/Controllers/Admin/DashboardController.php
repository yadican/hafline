<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'title' => 'Gösterge paneli',
            'modules' => [
                ['label' => 'Şantiye', 'hint' => 'Sahalar'],
                ['label' => 'Plaka', 'hint' => 'Araçlar'],
                ['label' => 'Taşıma', 'hint' => 'Seferler'],
                ['label' => 'Kasa', 'hint' => 'Nakit'],
            ],
        ]);
    }
}
