@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Araç sahipleri"
        icon="fa-solid fa-id-card"
        description="Araçların sahiplerini ve iletişim bilgilerini yönetin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Filo' => null, 'Araç sahipleri' => null]"
    >
        @include('admin.vehicle-owners.partials.index.toolbar')
        <x-table.list>
            @include('admin.vehicle-owners.partials.index.table-head')
            @include('admin.vehicle-owners.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$vehicleOwners" />
    </x-panel.page>
@endsection
