@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Web işlem logları"
        icon="fa-solid fa-clipboard-list"
        description="Admin panelindeki tüm HTTP hareketlerini inceleyin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Web işlem logları' => null]"
    >
        @include('admin.audit-logs.partials.index.toolbar')
        <x-table.list>
            @include('admin.audit-logs.partials.index.table-head')
            @include('admin.audit-logs.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$logs" />
    </x-panel.page>
@endsection
