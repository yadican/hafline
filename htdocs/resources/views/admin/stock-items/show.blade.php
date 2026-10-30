@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Stok kalemi detayı"
        icon="fa-solid fa-boxes-stacked"
        :description="$stockItem->name"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Stok' => route('admin.stock-items.index'), 'Detay' => null]"
        :back="route('admin.stock-items.index')"
    >
        <x-slot:actions>
            @can('edit-stock-item')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.stock-items.edit', $stockItem)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        @include('admin.stock-items.partials.show.details')

        @if ($stock !== null)
            @include('admin.stock-items.partials.show.stock')
            @include('admin.stock-items.partials.show.movements')
        @endif
    </x-panel.page>
@endsection
