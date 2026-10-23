@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Rol detayı"
        icon="fa-solid fa-user-shield"
        :description="$role->name"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Yetki yönetimi' => null, 'Roller' => route('admin.roles.index'), 'Detay' => null]"
        :back="route('admin.roles.index')"
    >
        <x-slot:actions>
            @can('edit-role')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.roles.edit', $role)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        @include('admin.roles.partials.show.details')
    </x-panel.page>
@endsection
