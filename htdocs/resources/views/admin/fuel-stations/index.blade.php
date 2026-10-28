@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yakıt kaynakları"
        icon="fa-solid fa-gas-pump"
        description="İstasyonları ve kendi depolarımızı, fiyatlarını ve depo stoğunu yönetin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Yakıt' => null, 'Yakıt kaynakları' => null]"
    >
        @include('admin.fuel-stations.partials.index.toolbar')
        <x-table.list>
            @include('admin.fuel-stations.partials.index.table-head')
            @include('admin.fuel-stations.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$fuelStations" />
    </x-panel.page>
@endsection
