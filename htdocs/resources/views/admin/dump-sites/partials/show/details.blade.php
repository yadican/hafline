<div class="panel-form-grid is-detail">
    <x-form.group legend="Döküm yeri" icon="fa-solid fa-dumpster" description="Döküm ücretleri işletmecinin cari hesabına yazılır">
        <x-form.field label="Ad">
            <div>{{ $dumpSite->name }}</div>
        </x-form.field>
        <x-form.field label="İşletmeci (cari)">
            <div>{{ $dumpSite->party?->name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Güzergah">
            <div>
                @if ($dumpSite->routes_count)
                    @can('view-haul-route')
                        <a href="{{ route('admin.haul-routes.index', ['dump_site' => $dumpSite->id]) }}" title="Bu döküm yerinin güzergahlarını göster">{{ $dumpSite->routes_count }}</a>
                    @else
                        {{ $dumpSite->routes_count }}
                    @endcan
                @else
                    —
                @endif
            </div>
        </x-form.field>
        <x-form.field label="Durum">
            <div><x-ui.active-badge :active="$dumpSite->active" /></div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="Döküm ücretleri" icon="fa-solid fa-layer-group" description="Sefer başına araç sınıfı döküm ücreti">
        <x-form.field label="Sınıf fiyatları">
            <div><x-ui.class-prices :prices="$dumpSite->classPrices" empty="Ücret yok" /></div>
        </x-form.field>
    </x-form.group>
</div>
