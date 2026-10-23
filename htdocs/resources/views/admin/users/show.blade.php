@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Kullanıcı detayı"
        icon="fa-solid fa-user"
        :description="$user->name"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Yetki yönetimi' => null, 'Kullanıcılar' => route('admin.users.index'), 'Detay' => null]"
        :back="route('admin.users.index')"
    >
        <x-slot:actions>
            @can('edit-user')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.users.edit', $user)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        @include('admin.users.partials.show.details')
    </x-panel.page>
@endsection
