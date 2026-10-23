@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Şantiye detayı"
        icon="fa-solid fa-helmet-safety"
        :description="$site->name"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Saha' => null, 'Şantiyeler' => route('admin.sites.index'), 'Detay' => null]"
        :back="route('admin.sites.index')"
    >
        <x-slot:actions>
            @can('edit-site')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.sites.edit', $site)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        @include('admin.sites.partials.show.details')
    </x-panel.page>
@endsection
