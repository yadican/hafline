@php
    use App\Enums\PartyRole;

    $dumpSites = $site->routes->pluck('dumpSite')->filter()->unique('id')->sortBy('name');
@endphp

<div class="panel-form-grid">
    <x-form.group legend="Şantiye" icon="fa-solid fa-helmet-safety" description="Şantiye adı ve kullanım durumu">
        <x-form.field label="Ad">
            <div>{{ $site->name }}</div>
        </x-form.field>
        <x-form.field label="Durum">
            <div><x-ui.active-badge :active="$site->active" /></div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="Firmalar" icon="fa-solid fa-handshake" description="Bu şantiyede çalışılan cari kartlar">
        @foreach (PartyRole::forSites() as $role)
            @php
                $roleParties = $site->parties->where('pivot.role', $role->value)->sortBy('name');
            @endphp
            <x-form.field :label="$role->pluralLabel()">
                @forelse ($roleParties as $party)
                    <div>{{ $party->name }}</div>
                @empty
                    <div>—</div>
                @endforelse
            </x-form.field>
        @endforeach
    </x-form.group>
</div>

<div class="panel-form-grid panel-form-grid-after">
    <x-form.group legend="Döküm yerleri" icon="fa-solid fa-dumpster" description="Güzergahı tanımlı döküm yerleri">
        @forelse ($dumpSites as $dumpSite)
            <div>{{ $dumpSite->name }}</div>
        @empty
            <p class="panel-hint">Henüz güzergah yok.</p>
        @endforelse
        @can('view-haul-route')
            <x-ui.button icon="fa-solid fa-route" :href="route('admin.haul-routes.index', ['site' => $site->id])">Güzergahlar</x-ui.button>
        @endcan
    </x-form.group>

    <x-form.group legend="Akaryakıt istasyonları" icon="fa-solid fa-gas-pump" description="Bu şantiyeye bağlı istasyonlar">
        @forelse ($site->fuelStations as $station)
            <div>{{ $station->name }}</div>
        @empty
            <p class="panel-hint">Bağlı istasyon yok.</p>
        @endforelse
    </x-form.group>
</div>
