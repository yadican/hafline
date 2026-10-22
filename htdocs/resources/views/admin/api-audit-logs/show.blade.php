@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="API işlem logu"
        icon="fa-solid fa-satellite-dish"
        description="İstek detayları, payload ve bağlam."
        :breadcrumbs="[
            'Gösterge paneli' => route('admin.dashboard'),
            'API işlem logları' => route('admin.api-audit-logs.index'),
            'Detay' => null,
        ]"
        :back="route('admin.api-audit-logs.index')"
    >
        @include('admin.audit-logs.partials.show.details')
    </x-panel.page>
@endsection
