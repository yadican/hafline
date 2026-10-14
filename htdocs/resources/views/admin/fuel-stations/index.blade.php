@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Akaryakıt istasyonları"
        icon="fa-solid fa-gas-pump"
        description="Yakıt fişlerinde kullanılan istasyonları ve birim fiyatlarını yönetin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Saha' => null, 'Akaryakıt istasyonları' => null]"
    >
        @include('admin.fuel-stations.partials.index.toolbar')
        <x-table.list>
            @include('admin.fuel-stations.partials.index.table-head')
            @include('admin.fuel-stations.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$fuelStations" />
    </x-panel.page>
@endsection
