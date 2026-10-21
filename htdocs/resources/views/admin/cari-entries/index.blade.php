@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Cari hareketleri"
        icon="fa-solid fa-right-left"
        description="Satıcı, alıcı, petrol, piyasa, plaka, araç sahibi, döküm yeri, şantiye ve kasa girişlerini tek listeden yönetin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Cari' => null, 'Cari hareketleri' => null]"
    >
        @include('admin.cari-entries.partials.index.toolbar')
        <x-table.list>
            @include('admin.cari-entries.partials.index.table')
        </x-table.list>
        <x-table.pager :items="$entries" />
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/cari-entries.js')
@endpush
