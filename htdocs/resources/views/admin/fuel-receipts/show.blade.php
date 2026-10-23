@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yakıt fişi detayı"
        icon="fa-solid fa-gas-pump"
        :description="($receipt->ticket_no ?: 'Numarasız').' fiş · '.$receipt->issued_on->format('d.m.Y')"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Yakıt fişleri' => route('admin.fuel-receipts.index'), 'Detay' => null]"
        :back="route('admin.fuel-receipts.index')"
    >
        <x-slot:actions>
            @can('edit-fuel-receipt')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.fuel-receipts.edit', $receipt)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        @include('admin.fuel-receipts.partials.show.details')
    </x-panel.page>
@endsection
