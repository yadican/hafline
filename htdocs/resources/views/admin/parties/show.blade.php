@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Cari kart detayı"
        :icon="$party->type->icon()"
        :description="$party->name.' · '.$party->type->label()"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Cari' => null, $party->type->pluralLabel() => route($party->type->indexRoute()), 'Detay' => null]"
        :back="route($party->type->indexRoute())"
    >
        <x-slot:actions>
            @can('edit-party')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.parties.edit', $party)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        @include('admin.parties.partials.show.details')
        @include('admin.parties.partials.edit.vehicles')
        @include('admin.parties.partials.edit.sites')
        @include('admin.parties.partials.edit.statement')
    </x-panel.page>
@endsection
