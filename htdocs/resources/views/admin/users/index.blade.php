@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Kullanıcılar"
        icon="fa-solid fa-users"
        description="Yönetim paneli kullanıcılarını ve rollerini yönetin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Yetki yönetimi' => null, 'Kullanıcılar' => null]"
    >
        @include('admin.users.partials.index.toolbar')
        <x-table.list>
            @include('admin.users.partials.index.table-head')
            @include('admin.users.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$users" />
    </x-panel.page>
@endsection
