@php
    $currentOwner = $vehicle->ownerships->firstWhere('ended_on', null)?->party;
@endphp

<div class="panel-form-grid is-detail">
    <x-form.group legend="Araç" icon="fa-solid fa-truck" description="Plaka, sınıf ve kullanım durumu">
        <x-form.field label="Plaka">
            <div><span class="panel-plate-text">{{ $vehicle->plate }}</span></div>
        </x-form.field>
        <x-form.field label="Sınıf">
            <div>{{ $vehicle->vehicleClass?->name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Durum">
            <div><x-ui.active-badge :active="$vehicle->active" /></div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="Sahip ve şoför" icon="fa-solid fa-id-badge" description="Güncel sahip ve aracı kullanan şoför">
        <x-form.field label="Güncel sahip">
            <div>
                @if ($currentOwner && ! $currentOwner->trashed())
                    <a href="{{ route('admin.parties.show', $currentOwner) }}">{{ $currentOwner->name }}</a>
                @else
                    {{ $currentOwner?->name ?: '—' }}
                @endif
            </div>
        </x-form.field>
        <x-form.field label="Şoför">
            <div>{{ $vehicle->driver_name ?: '—' }}</div>
        </x-form.field>
    </x-form.group>
</div>
