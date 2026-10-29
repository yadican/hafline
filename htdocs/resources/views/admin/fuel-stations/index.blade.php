@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yakıt yerleri"
        icon="fa-solid fa-gas-pump"
        description="İstasyonları ve saha pompalarını, fiyatlarını ve pompa stoğunu yönetin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Yakıt' => null, 'Yakıt yerleri' => null]"
    >
        @include('admin.fuel-stations.partials.index.toolbar')
        <x-table.list>
            @include('admin.fuel-stations.partials.index.table-head')
            @include('admin.fuel-stations.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$fuelStations" />
    </x-panel.page>
@endsection
