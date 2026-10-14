@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Araçlar"
        icon="fa-solid fa-truck"
        description="Plaka, sahip, sınıf ve şoför bilgileriyle araçları yönetin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Filo' => null, 'Araçlar' => null]"
    >
        @include('admin.vehicles.partials.index.toolbar')
        <x-table.list>
            @include('admin.vehicles.partials.index.table-head')
            @include('admin.vehicles.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$vehicles" />
    </x-panel.page>
@endsection
