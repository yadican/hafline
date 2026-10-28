@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni sefer"
        icon="fa-solid fa-plus"
        description="Kaydedilince müşteri borçlanır; nakliyeci ve referans alacaklanır."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Nakliye' => null, 'Seferler' => route('admin.shipments.index'), 'Yeni sefer' => null]"
        :back="route('admin.shipments.index')"
    >
        <form method="POST" action="{{ route('admin.shipments.store') }}" data-shipment-entry
              data-addresses-url="{{ route('admin.shipments.lookups.addresses') }}">
            @csrf
            <x-form.grid :model="['shipped_on' => today()->toDateString(), 'withholding_rate' => '0.00', 'carrier_withholding_rate' => '0.00']">
                @include('admin.shipments.partials.fields', ['shipment' => null])
                <x-form.actions label="Yeni sefer ekle" :cancel="route('admin.shipments.index')" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/shipments.js')
@endpush
