@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni hesap"
        icon="fa-solid fa-plus"
        description="Yeni bir kasa, banka hesabı, POS veya kredi kartı ekleyin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Kasa / Banka / POS' => route('admin.banks.index'), 'Yeni' => null]"
        :back="route('admin.banks.index')"
    >
        @include('admin.banks.partials.create.form')
    </x-panel.page>
@endsection
