@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Kasa kategorisi detayı"
        icon="fa-solid fa-tags"
        :description="$cashCategory->name"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Tanımlar' => null, 'Kasa kategorileri' => route('admin.cash-categories.index'), 'Detay' => null]"
        :back="route('admin.cash-categories.index')"
    >
        <x-slot:actions>
            @can('edit-cash-category')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.cash-categories.edit', $cashCategory)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        @include('admin.cash-categories.partials.show.details')
    </x-panel.page>
@endsection
