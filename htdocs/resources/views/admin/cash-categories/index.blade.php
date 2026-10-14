@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Kasa kategorileri"
        icon="fa-solid fa-tags"
        description="Kasa giriş ve çıkışlarını gruplamak için kullanılan kategorileri yönetin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Tanımlar' => null, 'Kasa kategorileri' => null]"
    >
        @include('admin.cash-categories.partials.index.toolbar')
        <x-table.list>
            @include('admin.cash-categories.partials.index.table-head')
            @include('admin.cash-categories.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$cashCategories" />
    </x-panel.page>
@endsection
