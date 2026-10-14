@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="haul-list"
        heading="Taşıma fişleri"
        icon="fa-solid fa-truck-ramp-box"
        description="Taşıma fişlerini görüntüleyin, arayın ve yönetin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Taşıma fişleri' => null]"
    >
        <div x-data="{ searchOpen: false }">
            @include('admin.hauls.partials.index.toolbar')
            @include('admin.hauls.partials.index.search')
        </div>
        <x-table.list class="haul-table">
            @include('admin.hauls.partials.index.table-head')
            @include('admin.hauls.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$hauls" />
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/hauls.js')
@endpush
