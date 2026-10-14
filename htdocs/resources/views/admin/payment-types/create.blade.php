@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni ödeme tipi"
        icon="fa-solid fa-plus"
        description="Ödeme ve tahsilat ekranlarında seçilecek yeni bir ödeme tipi ekleyin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Tanımlar' => null, 'Ödeme tipleri' => route('admin.payment-types.index'), 'Yeni' => null]"
        :back="route('admin.payment-types.index')"
    >
        @include('admin.payment-types.partials.create.form')
    </x-panel.page>
@endsection
