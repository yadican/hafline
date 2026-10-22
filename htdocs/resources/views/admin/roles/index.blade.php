@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Roller"
        icon="fa-solid fa-user-shield"
        description="Kullanıcılara atanan rolleri ve izinlerini yönetin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Yetki yönetimi' => null, 'Roller' => null]"
    >
        @include('admin.roles.partials.index.toolbar')
        <x-table.list>
            @include('admin.roles.partials.index.table-head')
            @include('admin.roles.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$roles" />
    </x-panel.page>
@endsection
