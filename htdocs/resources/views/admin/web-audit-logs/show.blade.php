@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Web işlem logu"
        icon="fa-solid fa-clipboard-list"
        description="İstek detayları, payload ve bağlam."
        :breadcrumbs="[
            'Gösterge paneli' => route('admin.dashboard'),
            'Web işlem logları' => route('admin.web-audit-logs.index'),
            'Detay' => null,
        ]"
        :back="route('admin.web-audit-logs.index')"
    >
        @include('admin.audit-logs.partials.show.details')
    </x-panel.page>
@endsection
