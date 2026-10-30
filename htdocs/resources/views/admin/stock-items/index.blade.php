@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Stok kalemleri"
        icon="fa-solid fa-boxes-stacked"
        description="Fatura ve stok hareketlerinde kullanılacak kalem kartlarını yönetin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Stok' => null]"
    >
        @include('admin.stock-items.partials.index.toolbar')
        <x-table.list>
            @include('admin.stock-items.partials.index.table-head')
            @include('admin.stock-items.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$stockItems" />
    </x-panel.page>
@endsection
