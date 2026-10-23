@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Akaryakıt istasyonu detayı"
        icon="fa-solid fa-gas-pump"
        :description="$fuelStation->name"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Saha' => null, 'Akaryakıt istasyonları' => route('admin.fuel-stations.index'), 'Detay' => null]"
        :back="route('admin.fuel-stations.index')"
    >
        <x-slot:actions>
            @can('edit-fuel-station')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.fuel-stations.edit', $fuelStation)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        @include('admin.fuel-stations.partials.show.details')
    </x-panel.page>
@endsection
