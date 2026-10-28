@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni sevk yeri"
        icon="fa-solid fa-plus"
        description="Kaydettikten sonra düzenleme ekranından satış fiyatlarını girin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Nakliye' => null, 'Sevk yerleri' => route('admin.delivery-addresses.index'), 'Yeni' => null]"
        :back="route('admin.delivery-addresses.index')"
    >
        <form method="POST" action="{{ route('admin.delivery-addresses.store') }}">
            @csrf
            <x-form.grid>
                @include('admin.delivery-addresses.partials.form')
                <x-form.actions label="Kaydet" :cancel="route('admin.delivery-addresses.index')" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection
