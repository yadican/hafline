@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Hesabı düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$bank->name.' bilgilerini güncelleyin.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Tanımlar' => null, 'Kasa / Banka / POS' => route('admin.banks.index'), 'Düzenle' => null]"
        :back="route('admin.banks.index')"
    >
        @include('admin.banks.partials.edit.form')
    </x-panel.page>
@endsection
