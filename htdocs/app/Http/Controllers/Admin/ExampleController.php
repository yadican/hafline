<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ExampleRequest;
use App\Models\Example;
use App\Services\ExampleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExampleController extends AdminController
{
    public function __construct(private ExampleService $examples) {}

    public function index(): View
    {
        return view('admin.examples.index', [
            'title' => 'Örnek kayıtlar',
            'examples' => $this->examples->paginate(),
        ]);
    }

    public function create(): View
    {
        return view('admin.examples.create', [
            'title' => 'Yeni örnek kayıt',
        ]);
    }

    public function store(ExampleRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->examples->create($request->validated()),
            'admin.examples.index',
            'Kayıt oluşturuldu.',
        );
    }

    public function edit(Example $example): View
    {
        return view('admin.examples.edit', [
            'title' => 'Örnek kaydı düzenle',
            'example' => $example,
        ]);
    }

    public function update(ExampleRequest $request, Example $example): RedirectResponse
    {
        return $this->complete(
            fn () => $this->examples->update($example, $request->validated()),
            'admin.examples.index',
            'Kayıt güncellendi.',
        );
    }

    public function destroy(Example $example): RedirectResponse
    {
        return $this->complete(
            fn () => $this->examples->delete($example),
            'admin.examples.index',
            'Kayıt silindi.',
        );
    }
}
