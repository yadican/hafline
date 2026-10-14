@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Kasa kategorisini düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$cashCategory->name.' kategorisinin bilgilerini güncelleyin.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Tanımlar' => null, 'Kasa kategorileri' => route('admin.cash-categories.index'), 'Düzenle' => null]"
        :back="route('admin.cash-categories.index')"
    >
        @include('admin.cash-categories.partials.edit.form')
    </x-panel.page>
@endsection
