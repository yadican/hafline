@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Stok sayımı detayı"
        icon="fa-solid fa-clipboard-check"
        :description="$count->stockItem?->name"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Stok sayımı' => route('admin.stock-counts.index'), 'Detay' => null]"
        :back="route('admin.stock-counts.index')"
    >
        <x-slot:actions>
            @can('edit-stock-count')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.stock-counts.edit', $count)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        @include('admin.stock-counts.partials.show.details')
    </x-panel.page>
@endsection
