@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Araç sınıfı detayı"
        icon="fa-solid fa-layer-group"
        :description="$vehicleClass->name"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Filo' => null, 'Araç sınıfları' => route('admin.vehicle-classes.index'), 'Detay' => null]"
        :back="route('admin.vehicle-classes.index')"
    >
        <x-slot:actions>
            @can('edit-vehicle-class')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.vehicle-classes.edit', $vehicleClass)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        @include('admin.vehicle-classes.partials.show.details')
    </x-panel.page>
@endsection
