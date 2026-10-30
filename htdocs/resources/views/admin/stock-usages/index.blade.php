@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Stok kullanımları"
        icon="fa-solid fa-box-open"
        description="Kendi kullanımımız için stoktan çıkan kalemler; maliyet şantiye / araç gideridir."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Stok kullanımı' => null]"
    >
        @include('admin.stock-usages.partials.index.toolbar')
        <x-table.list>
            @include('admin.stock-usages.partials.index.table-head')
            @include('admin.stock-usages.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$usages" />
    </x-panel.page>
@endsection
