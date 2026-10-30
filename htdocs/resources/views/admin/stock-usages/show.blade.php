@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Stok kullanımı detayı"
        icon="fa-solid fa-box-open"
        :description="$usage->stockItem?->name"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Stok kullanımı' => route('admin.stock-usages.index'), 'Detay' => null]"
        :back="route('admin.stock-usages.index')"
    >
        <x-slot:actions>
            @can('edit-stock-usage')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.stock-usages.edit', $usage)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        @include('admin.stock-usages.partials.show.details')
    </x-panel.page>
@endsection
