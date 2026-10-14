@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Bankalar"
        icon="fa-solid fa-building-columns"
        description="Çek, senet ve kredi kayıtlarında kullanılan bankaları yönetin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Tanımlar' => null, 'Bankalar' => null]"
    >
        @include('admin.banks.partials.index.toolbar')
        <x-table.list>
            @include('admin.banks.partials.index.table-head')
            @include('admin.banks.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$banks" />
    </x-panel.page>
@endsection
