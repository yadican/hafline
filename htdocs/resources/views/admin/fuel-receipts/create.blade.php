@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni yakıt fişi"
        icon="fa-solid fa-plus"
        description="Litre × birim fiyat araç sahibinin carisine borç yazılır. Akaryakıt firmasına ödeme bu fişten yapılmaz."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Yakıt fişleri' => route('admin.fuel-receipts.index'), 'Yeni fiş' => null]"
        :back="route('admin.fuel-receipts.index')"
    >
        <form method="POST" action="{{ route('admin.fuel-receipts.store') }}" data-fuel-entry data-station-url="{{ route('admin.fuel-receipts.lookups.station', ['fuelStation' => '__ID__']) }}">
            @csrf
            <x-form.grid>
                @include('admin.fuel-receipts.partials.form.fields', ['receipt' => null])
                <x-form.actions label="Kaydet" :cancel="route('admin.fuel-receipts.index')" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/fuel-receipts.js')
@endpush
