@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Araç detayı"
        icon="fa-solid fa-truck"
        :description="$vehicle->plate"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Filo' => null, 'Araçlar' => route('admin.vehicles.index'), 'Detay' => null]"
        :back="route('admin.vehicles.index')"
    >
        <x-slot:actions>
            @can('edit-vehicle')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.vehicles.edit', $vehicle)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        @include('admin.vehicles.partials.show.details')
        @include('admin.vehicles.partials.show.ownerships')
        @if ($vehicle->isHourly() || $vehicle->rates->isNotEmpty())
            @include('admin.vehicles.partials.show.rates')
        @endif
    </x-panel.page>
@endsection
