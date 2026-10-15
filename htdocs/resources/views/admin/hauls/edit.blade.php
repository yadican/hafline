@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Taşıma fişini düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$haul->ticket_no.' numaralı fişi güncelleyin. Plaka veya güzergah değişirse tutar güncel tarifeyle yeniden hesaplanır.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Taşıma fişleri' => route('admin.hauls.index'), 'Düzenle' => null]"
        :back="route('admin.hauls.index')"
    >
        @include('admin.hauls.partials.edit.form')
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/hauls.js')
@endpush
