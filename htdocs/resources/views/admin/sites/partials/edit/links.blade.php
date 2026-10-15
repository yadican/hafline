@php
    $dumpSites = $site->routes->pluck('dumpSite')->filter()->unique('id')->sortBy('name');
@endphp

<div class="panel-form-grid panel-form-grid-after">
    <x-form.group legend="Döküm yerleri" icon="fa-solid fa-dumpster" description="Güzergahı tanımlı döküm yerleri">
        @forelse ($dumpSites as $dumpSite)
            <div>{{ $dumpSite->name }}</div>
        @empty
            <p class="panel-hint">Henüz güzergah yok.</p>
        @endforelse
        <x-ui.button icon="fa-solid fa-route" :href="route('admin.haul-routes.index', ['site' => $site->id])">Güzergahlar</x-ui.button>
    </x-form.group>

    <x-form.group legend="Akaryakıt istasyonları" icon="fa-solid fa-gas-pump" description="Bu şantiyeye bağlı istasyonlar">
        @forelse ($site->fuelStations as $station)
            <div><a href="{{ route('admin.fuel-stations.edit', $station) }}">{{ $station->name }}</a></div>
        @empty
            <p class="panel-hint">Bağlı istasyon yok. İstasyon formundan şantiye seçebilirsiniz.</p>
        @endforelse
    </x-form.group>
</div>
