@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="API işlem logları"
        icon="fa-solid fa-satellite-dish"
        description="API üzerinden gelen tüm HTTP hareketlerini inceleyin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'API işlem logları' => null]"
    >
        @include('admin.audit-logs.partials.index.toolbar')
        <x-table.list>
            @include('admin.audit-logs.partials.index.table-head')
            @include('admin.audit-logs.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$logs" />
    </x-panel.page>
@endsection
