@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Cari hareketi detayı"
        icon="fa-solid fa-right-left"
        :description="$kind->label().' · '.$details['occurred_on']->format('d.m.Y')"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Cari hareketleri' => route('admin.cari-entries.index'), 'Detay' => null]"
        :back="route('admin.cari-entries.index')"
    >
        <x-slot:actions>
            @can('edit-cari-entry')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.cari-entries.edit', ['kind' => $kind->value, 'entry' => $entry])">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        @include('admin.cari-entries.partials.show.details')
    </x-panel.page>
@endsection
