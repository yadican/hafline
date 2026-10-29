@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Kasa / Banka / POS"
        icon="fa-solid fa-wallet"
        description="Şirketin para tuttuğu hesaplar: kasalar, banka hesapları, POS cihazları ve kredi kartları."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Kasa / Banka / POS' => null]"
    >
        @include('admin.banks.partials.index.toolbar')
        <x-table.list>
            @include('admin.banks.partials.index.table-head')
            @include('admin.banks.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$banks" />
    </x-panel.page>
@endsection
