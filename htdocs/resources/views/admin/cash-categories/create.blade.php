@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni kasa kategorisi"
        icon="fa-solid fa-plus"
        description="Kasa hareketlerinde seçilecek yeni bir kategori ekleyin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Tanımlar' => null, 'Kasa kategorileri' => route('admin.cash-categories.index'), 'Yeni' => null]"
        :back="route('admin.cash-categories.index')"
    >
        @include('admin.cash-categories.partials.create.form')
    </x-panel.page>
@endsection
