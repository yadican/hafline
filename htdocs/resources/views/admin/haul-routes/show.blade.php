@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Güzergah detayı"
        icon="fa-solid fa-route"
        :description="$haulRoute->site?->name.' → '.$haulRoute->dumpSite?->name"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Saha' => null, 'Güzergahlar' => route('admin.haul-routes.index'), 'Detay' => null]"
        :back="route('admin.haul-routes.index')"
    >
        <x-slot:actions>
            @can('edit-haul-route')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.haul-routes.edit', $haulRoute)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        @include('admin.haul-routes.partials.show.details')
    </x-panel.page>
@endsection
