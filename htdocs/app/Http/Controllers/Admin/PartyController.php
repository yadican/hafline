<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PartyRole;
use App\Enums\PartyType;
use App\Http\Requests\Admin\PartyRequest;
use App\Http\Requests\Admin\PartySearchRequest;
use App\Models\Party;
use App\Services\PartyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartyController extends AdminController
{
    public function __construct(private PartyService $parties) {}

    public function index(PartySearchRequest $request, string $type): View
    {
        $type = PartyType::from($type);
        $filters = $request->filters();

        return view('admin.parties.index', [
            'title' => $type->pluralLabel(),
            'type' => $type,
            'parties' => $this->parties->paginate([...$filters, 'type' => $type->value], $request->sort()),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        $type = PartyType::tryFrom((string) $request->query('type')) ?? PartyType::Person;

        return view('admin.parties.create', [
            'title' => $type === PartyType::Person ? 'Yeni kişi' : 'Yeni şirket',
            'type' => $type,
            'values' => ['roles' => array_filter([PartyRole::tryFrom((string) $request->query('role'))])],
            'locations' => $this->parties->locationOptions(old('city_id'), old('district_id')),
        ]);
    }

    public function store(PartyRequest $request): RedirectResponse
    {
        $type = $request->partyType();

        return $this->complete(
            fn () => $this->parties->create($type, $request->validated()),
            $type->indexRoute(),
            $type === PartyType::Person ? 'Kişi oluşturuldu.' : 'Şirket oluşturuldu.',
        );
    }

    public function edit(Party $party): View
    {
        $party->load(['ownerships' => fn ($query) => $query->with('vehicle')->orderByDesc('started_on'), 'sites']);

        $values = $this->parties->formValues($party);

        return view('admin.parties.edit', [
            'title' => 'Cari kartı düzenle',
            'party' => $party,
            'values' => $values,
            'locations' => $this->parties->locationOptions(
                old('city_id', $values['city_id']),
                old('district_id', $values['district_id']),
            ),
            'lockedRoles' => $this->parties->lockedRoles($party),
            'entries' => $this->parties->statement($party),
            'totals' => $this->parties->totals($party),
        ]);
    }

    public function update(PartyRequest $request, Party $party): RedirectResponse
    {
        return $this->complete(
            fn () => $this->parties->update($party, $request->validated()),
            'admin.parties.edit',
            'Cari kart güncellendi.',
            [$party],
        );
    }

    public function destroy(Party $party): RedirectResponse
    {
        return $this->complete(
            fn () => $this->parties->delete($party),
            $party->type->indexRoute(),
            'Cari kart silindi.',
        );
    }
}
