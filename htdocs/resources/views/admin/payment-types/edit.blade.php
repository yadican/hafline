@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Ödeme tipini düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$paymentType->name.' ödeme tipinin bilgilerini güncelleyin.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Tanımlar' => null, 'Ödeme tipleri' => route('admin.payment-types.index'), 'Düzenle' => null]"
        :back="route('admin.payment-types.index')"
    >
        @include('admin.payment-types.partials.edit.form')
    </x-panel.page>
@endsection
