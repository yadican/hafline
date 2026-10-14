@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Örnek kayıtlar"
        icon="fa-solid fa-layer-group"
        description="Tüm kayıtları görüntüleyin, arayın ve yönetin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Örnek kayıtlar' => null]"
    >
        @include('admin.examples.partials.index.toolbar')
        <x-table.list>
            @include('admin.examples.partials.index.table-head')
            @include('admin.examples.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$examples" />
    </x-panel.page>
@endsection
