<?php

namespace App\Http\Controllers\Admin\Permission;

use App\Http\Controllers\Admin\AdminController;
use App\Models\Permission;
use App\Support\PermissionGroups;
use Illuminate\View\View;

class PermissionController extends AdminController
{
    protected array $permissionMap = [
        'index' => 'view-permission',
    ];

    public function index(): View
    {

        return view('admin.permissions.index', [
            'title' => 'İzinler',
            'permissionGroups' => PermissionGroups::grouped(
                Permission::query()->where('guard_name', 'web')->orderBy('name')->get(),
                'web',
            ),
        ]);
    }
}
