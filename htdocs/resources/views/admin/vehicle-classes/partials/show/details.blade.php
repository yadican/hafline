<div class="panel-form-grid is-detail">
    <x-form.group legend="Araç sınıfı" icon="fa-solid fa-layer-group" description="Sınıf adı ve kullanım durumu">
        <x-form.field label="Ad">
            <div>{{ $vehicleClass->name }}</div>
        </x-form.field>
        <x-form.field label="Ücretlendirme">
            <div>{{ $vehicleClass->hourly ? 'Saatlik' : 'Sefer' }}</div>
        </x-form.field>
        <x-form.field label="Araç sayısı">
            <div>
                @if ($vehicleClass->vehicles_count)
                    <a href="{{ route('admin.vehicles.index', ['class' => $vehicleClass->id]) }}" title="Bu sınıftaki araçları göster">{{ $vehicleClass->vehicles_count }}</a>
                @else
                    —
                @endif
            </div>
        </x-form.field>
        <x-form.field label="Durum">
            <div><x-ui.active-badge :active="$vehicleClass->active" /></div>
        </x-form.field>
    </x-form.group>
</div>
