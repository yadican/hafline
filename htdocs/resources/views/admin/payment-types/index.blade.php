@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Ödeme tipleri"
        icon="fa-solid fa-money-check-dollar"
        description="Ödeme ve tahsilatlarda seçilen ödeme tiplerini yönetin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Tanımlar' => null, 'Ödeme tipleri' => null]"
    >
        @include('admin.payment-types.partials.index.toolbar')
        <x-table.list>
            @include('admin.payment-types.partials.index.table-head')
            @include('admin.payment-types.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$paymentTypes" />
    </x-panel.page>
@endsection
