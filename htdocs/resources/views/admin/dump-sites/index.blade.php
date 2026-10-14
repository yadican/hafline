@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Döküm yerleri"
        icon="fa-solid fa-dumpster"
        description="Hafriyatın döküldüğü sahaları yönetin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Saha' => null, 'Döküm yerleri' => null]"
    >
        @include('admin.dump-sites.partials.index.toolbar')
        <x-table.list>
            @include('admin.dump-sites.partials.index.table-head')
            @include('admin.dump-sites.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$dumpSites" />
    </x-panel.page>
@endsection
