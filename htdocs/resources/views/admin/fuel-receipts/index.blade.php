@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="haul-list"
        heading="Yakıt fişleri"
        icon="fa-solid fa-gas-pump"
        description="Araçlara alınan yakıtı görüntüleyin. Tutar, araç sahibinin carisine borç yazılır."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Yakıt fişleri' => null]"
    >
        <div x-data="{ searchOpen: false }">
            @include('admin.fuel-receipts.partials.index.toolbar')
            @include('admin.fuel-receipts.partials.index.search')
        </div>
        <x-table.list class="haul-table">
            @include('admin.fuel-receipts.partials.index.table-head')
            @include('admin.fuel-receipts.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$receipts" />
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/fuel-receipts.js')
@endpush
