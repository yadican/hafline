@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Taşıma fişi detayı"
        icon="fa-solid fa-truck-ramp-box"
        :description="$haul->ticket_no.' numaralı fiş'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Taşıma fişleri' => route('admin.hauls.index'), 'Detay' => null]"
        :back="route('admin.hauls.index')"
    >
        <x-slot:actions>
            @can('edit-haul')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.hauls.edit', $haul)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        @include('admin.hauls.partials.show.details')
    </x-panel.page>
@endsection
