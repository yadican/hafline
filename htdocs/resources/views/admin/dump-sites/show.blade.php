@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Döküm yeri detayı"
        icon="fa-solid fa-dumpster"
        :description="$dumpSite->name"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Saha' => null, 'Döküm yerleri' => route('admin.dump-sites.index'), 'Detay' => null]"
        :back="route('admin.dump-sites.index')"
    >
        <x-slot:actions>
            @can('edit-dump-site')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.dump-sites.edit', $dumpSite)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        @include('admin.dump-sites.partials.show.details')
    </x-panel.page>
@endsection
