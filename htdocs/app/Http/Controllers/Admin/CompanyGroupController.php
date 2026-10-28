<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\CompanyGroupRequest;
use App\Http\Requests\Admin\CompanyGroupSearchRequest;
use App\Models\CompanyGroup;
use App\Services\CompanyGroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompanyGroupController extends AdminController
{
    protected ?string $permissionResource = 'company-group';

    public function __construct(private CompanyGroupService $groups) {}

    public function index(CompanyGroupSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.company-groups.index', [
            'title' => 'Şirket grupları',
            'groups' => $this->groups->paginate($filters, $request->sort()),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.company-groups.create', [
            'title' => 'Yeni şirket grubu',
        ]);
    }

    public function store(CompanyGroupRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->groups->create($request->validated()),
            'admin.company-groups.index',
            'Şirket grubu oluşturuldu.',
        );
    }

    public function show(CompanyGroup $companyGroup): View
    {
        $companyGroup->load(['companies.party']);

        return view('admin.company-groups.show', [
            'title' => 'Şirket grubu detayı',
            'group' => $companyGroup,
        ]);
    }

    public function edit(CompanyGroup $companyGroup): View
    {
        return view('admin.company-groups.edit', [
            'title' => 'Şirket grubunu düzenle',
            'group' => $companyGroup,
        ]);
    }

    public function update(CompanyGroupRequest $request, CompanyGroup $companyGroup): RedirectResponse
    {
        return $this->complete(
            fn () => $this->groups->update($companyGroup, $request->validated()),
            'admin.company-groups.index',
            'Şirket grubu güncellendi.',
        );
    }

    public function destroy(CompanyGroup $companyGroup): RedirectResponse
    {
        return $this->complete(
            fn () => $this->groups->delete($companyGroup),
            'admin.company-groups.index',
            'Şirket grubu silindi.',
        );
    }
}
