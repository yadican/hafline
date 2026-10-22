@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Personel ödeme girişi"
        icon="fa-solid fa-money-bill-transfer"
        description="Personele yapılan ödemeleri dönem bazında kaydedin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Personel' => null, 'Ödeme girişi' => null]"
    >
        @include('admin.employee-payments.partials.index.toolbar')
        <x-table.list>
            @include('admin.employee-payments.partials.index.table-head')
            @include('admin.employee-payments.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$payments" />
    </x-panel.page>
@endsection
