<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\SiteRequest;
use App\Http\Requests\Admin\SiteSearchRequest;
use App\Models\Site;
use App\Services\SiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SiteController extends AdminController
{
    protected ?string $permissionResource = 'site';

    public function __construct(private SiteService $sites) {}

    public function index(SiteSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.sites.index', [
            'title' => 'Şantiyeler',
            'sites' => $this->sites->paginate($filters, $request->sort()),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.sites.create', [
            'title' => 'Yeni şantiye',
            'parties' => $this->sites->partyOptions(),
        ]);
    }

    public function store(SiteRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->sites->create($request->validated()),
            'admin.sites.index',
            'Şantiye oluşturuldu.',
        );
    }

    public function show(Site $site): View
    {
        $site->load('parties', 'fuelStations', 'routes.dumpSite');

        return view('admin.sites.show', [
            'title' => 'Şantiye detayı',
            'site' => $site,
        ]);
    }

    public function edit(Site $site): View
    {
        $site->load('parties', 'fuelStations', 'routes.dumpSite');

        return view('admin.sites.edit', [
            'title' => 'Şantiyeyi düzenle',
            'site' => $site,
            'parties' => $this->sites->partyOptions($site),
            'partyValues' => $this->sites->partyValues($site),
        ]);
    }

    public function update(SiteRequest $request, Site $site): RedirectResponse
    {
        return $this->complete(
            fn () => $this->sites->update($site, $request->validated()),
            'admin.sites.index',
            'Şantiye güncellendi.',
        );
    }

    public function destroy(Site $site): RedirectResponse
    {
        return $this->complete(
            fn () => $this->sites->delete($site),
            'admin.sites.index',
            'Şantiye silindi.',
        );
    }
}
