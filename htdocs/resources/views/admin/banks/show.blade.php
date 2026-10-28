@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Hesap detayı"
        :icon="$bank->type->icon()"
        :description="$bank->name.' · '.$bank->type->label()"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Tanımlar' => null, 'Kasa / Banka / POS' => route('admin.banks.index'), 'Detay' => null]"
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
