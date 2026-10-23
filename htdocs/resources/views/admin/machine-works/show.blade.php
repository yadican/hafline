@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Makine çalışma fişi detayı"
        icon="fa-solid fa-person-digging"
        :description="$work->ticket_no.' numaralı fiş'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Makine çalışma fişleri' => route('admin.machine-works.index'), 'Detay' => null]"
        :back="route('admin.machine-works.index')"
    >
        <x-slot:actions>
            @can('edit-machine-work')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.machine-works.edit', $work)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        @include('admin.machine-works.partials.show.details')
    </x-panel.page>
@endsection
