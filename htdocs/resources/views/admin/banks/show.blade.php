@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Hesap detayı"
        :icon="$bank->type->icon()"
        :description="$bank->name.' · '.$bank->type->label()"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Kasa / Banka / POS' => route('admin.banks.index'), 'Detay' => null]"
        :back="route('admin.banks.index')"
    >
        <x-slot:actions>
            @if ($bank->active)
                @can('create-cari-entry')
                    <x-ui.button icon="fa-solid fa-right-left" :href="route('admin.cari-entries.create', ['kind' => 'account_transfer', 'bank_id' => $bank->id])">Bu hesaptan virman</x-ui.button>
                @endcan
            @endif
            @can('edit-bank')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.banks.edit', $bank)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        @include('admin.banks.partials.show.details')
    </x-panel.page>
@endsection
