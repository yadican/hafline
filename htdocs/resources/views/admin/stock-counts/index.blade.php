@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Stok sayımları"
        icon="fa-solid fa-clipboard-check"
        description="Sayılan miktar ile defter farkı stoğu düzeltir; cariye yazılmaz."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Stok sayımı' => null]"
    >
        @include('admin.stock-counts.partials.index.toolbar')
        <x-table.list>
            @include('admin.stock-counts.partials.index.table-head')
            @include('admin.stock-counts.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$counts" />
    </x-panel.page>
@endsection
