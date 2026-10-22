@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Personel ödemesini düzenle"
        icon="fa-solid fa-money-bill-transfer"
        :description="$payment->employee?->name"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Personel' => null, 'Ödeme girişi' => route('admin.employee-payments.index'), 'Düzenle' => null]"
        :back="route('admin.employee-payments.index')"
    >
        @include('admin.employee-payments.partials.edit.form')
    </x-panel.page>
@endsection
