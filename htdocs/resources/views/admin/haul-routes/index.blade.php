@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Güzergahlar ve fiyatlar"
        icon="fa-solid fa-route"
        description="Şantiyeden döküm yerine güzergahları ve sınıf fiyatlarını yönetin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Saha' => null, 'Güzergahlar' => null]"
    >
        @include('admin.haul-routes.partials.index.toolbar')
        <x-table.list>
            @include('admin.haul-routes.partials.index.table-head')
            @include('admin.haul-routes.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$haulRoutes" />
    </x-panel.page>
@endsection
