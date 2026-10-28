@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni sefer"
        icon="fa-solid fa-plus"
        description="Kaydedilince müşteri borçlanır; nakliyeci, ocak ve komisyoncu alacaklanır."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Nakliye' => null, 'Seferler' => route('admin.shipments.index'), 'Yeni sefer' => null]"
        :back="route('admin.shipments.index')"
    >
        <form method="POST" action="{{ route('admin.shipments.store') }}" data-shipment-entry data-prices-url="{{ route('admin.shipments.lookups.prices') }}"
              x-data="{ kind: @js(old('kind', 'material_sale')) }" @change="if ($event.target.name === 'kind') kind = $event.target.value">
            @csrf
            <x-form.grid :model="['kind' => 'material_sale', 'shipped_on' => today()->toDateString(), 'withholding_rate' => '0.00']">
                @include('admin.shipments.partials.fields', ['shipment' => null])
                <x-form.actions label="Kaydet" :cancel="route('admin.shipments.index')" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/shipments.js')
@endpush
