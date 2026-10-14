<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\DumpSiteRequest;
use App\Http\Requests\Admin\DumpSiteSearchRequest;
use App\Models\DumpSite;
use App\Services\DumpSiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DumpSiteController extends AdminController
{
    public function __construct(private DumpSiteService $dumpSites) {}

    public function index(DumpSiteSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.dump-sites.index', [
            'title' => 'Döküm yerleri',
            'dumpSites' => $this->dumpSites->paginate($filters, $request->sort()),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.dump-sites.create', [
            'title' => 'Yeni döküm yeri',
        ]);
    }

    public function store(DumpSiteRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->dumpSites->create($request->validated()),
            'admin.dump-sites.index',
            'Döküm yeri oluşturuldu.',
        );
    }

    public function edit(DumpSite $dumpSite): View
    {
        return view('admin.dump-sites.edit', [
            'title' => 'Döküm yerini düzenle',
            'dumpSite' => $dumpSite,
        ]);
    }

    public function update(DumpSiteRequest $request, DumpSite $dumpSite): RedirectResponse
    {
        return $this->complete(
            fn () => $this->dumpSites->update($dumpSite, $request->validated()),
            'admin.dump-sites.index',
            'Döküm yeri güncellendi.',
        );
    }

    public function destroy(DumpSite $dumpSite): RedirectResponse
    {
        return $this->complete(
            fn () => $this->dumpSites->delete($dumpSite),
            'admin.dump-sites.index',
            'Döküm yeri silindi.',
        );
    }
}
