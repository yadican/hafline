<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\CashCategoryRequest;
use App\Http\Requests\Admin\CashCategorySearchRequest;
use App\Models\CashCategory;
use App\Services\CashCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CashCategoryController extends AdminController
{
    public function __construct(private CashCategoryService $cashCategories) {}

    public function index(CashCategorySearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.cash-categories.index', [
            'title' => 'Kasa kategorileri',
            'cashCategories' => $this->cashCategories->paginate($filters, $request->sort()),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.cash-categories.create', [
            'title' => 'Yeni kasa kategorisi',
        ]);
    }

    public function store(CashCategoryRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->cashCategories->create($request->validated()),
            'admin.cash-categories.index',
            'Kasa kategorisi oluşturuldu.',
        );
    }

    public function edit(CashCategory $cashCategory): View
    {
        return view('admin.cash-categories.edit', [
            'title' => 'Kasa kategorisini düzenle',
            'cashCategory' => $cashCategory,
        ]);
    }

    public function update(CashCategoryRequest $request, CashCategory $cashCategory): RedirectResponse
    {
        return $this->complete(
            fn () => $this->cashCategories->update($cashCategory, $request->validated()),
            'admin.cash-categories.index',
            'Kasa kategorisi güncellendi.',
        );
    }

    public function destroy(CashCategory $cashCategory): RedirectResponse
    {
        return $this->complete(
            fn () => $this->cashCategories->delete($cashCategory),
            'admin.cash-categories.index',
            'Kasa kategorisi silindi.',
        );
    }
}
