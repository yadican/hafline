@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Personel prim girişi"
        icon="fa-solid fa-gift"
        description="Personele verilen primleri kaydedin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Personel' => null, 'Prim girişi' => null]"
    >
        @include('admin.employee-bonuses.partials.index.toolbar')
        <x-table.list>
            @include('admin.employee-bonuses.partials.index.table-head')
            @include('admin.employee-bonuses.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$bonuses" />
    </x-panel.page>
@endsection
