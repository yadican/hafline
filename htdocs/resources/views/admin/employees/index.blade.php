@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Personel kartları"
        icon="fa-solid fa-id-badge"
        description="Cari kişi kartlarına bağlı maaş ve iş dönemi bilgilerini yönetin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Personel' => null, 'Personel kartları' => null]"
    >
        @include('admin.employees.partials.index.toolbar')
        <x-table.list>
            @include('admin.employees.partials.index.table-head')
            @include('admin.employees.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$employees" />
    </x-panel.page>
@endsection
