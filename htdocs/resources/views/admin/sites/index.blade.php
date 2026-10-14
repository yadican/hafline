@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Şantiyeler"
        icon="fa-solid fa-helmet-safety"
        description="Hafriyatın alındığı şantiyeleri yönetin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Saha' => null, 'Şantiyeler' => null]"
    >
        @include('admin.sites.partials.index.toolbar')
        <x-table.list>
            @include('admin.sites.partials.index.table-head')
            @include('admin.sites.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$sites" />
    </x-panel.page>
@endsection
