@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="haul-list"
        heading="Makine çalışma fişleri"
        icon="fa-solid fa-person-digging"
        description="Makinelerin şantiyede çalıştığı saatleri görüntüleyin, arayın ve yönetin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Makine çalışma fişleri' => null]"
    >
        <div x-data="{ searchOpen: false }">
            @include('admin.machine-works.partials.index.toolbar')
            @include('admin.machine-works.partials.index.search')
        </div>
        <x-table.list class="haul-table">
            @include('admin.machine-works.partials.index.table-head')
            @include('admin.machine-works.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$works" />
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/machine-works.js')
@endpush
