@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni personel ödemesi"
        icon="fa-solid fa-plus"
        description="Personele yapılan ödemeyi kaydedin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Personel' => null, 'Ödeme girişi' => route('admin.employee-payments.index'), 'Yeni' => null]"
        :back="route('admin.employee-payments.index')"
    >
        @include('admin.employee-payments.partials.create.form')
    </x-panel.page>
@endsection
