@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Araç sınıfları"
        icon="fa-solid fa-layer-group"
        description="Fiyatlandırmada kullanılan araç sınıflarını (TIR, kamyon, damperli…) yönetin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Filo' => null, 'Araç sınıfları' => null]"
    >
        @include('admin.vehicle-classes.partials.index.toolbar')
        <x-table.list>
            @include('admin.vehicle-classes.partials.index.table-head')
            @include('admin.vehicle-classes.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$vehicleClasses" />
    </x-panel.page>
@endsection
