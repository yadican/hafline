@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Banka detayı"
        icon="fa-solid fa-building-columns"
        :description="$bank->name"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Tanımlar' => null, 'Bankalar' => route('admin.banks.index'), 'Detay' => null]"
        :back="route('admin.banks.index')"
    >
        <x-slot:actions>
            @can('edit-bank')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.banks.edit', $bank)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        @include('admin.banks.partials.show.details')
    </x-panel.page>
@endsection
