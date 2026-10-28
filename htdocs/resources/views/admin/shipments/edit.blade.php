@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Seferi düzenle"
        icon="fa-solid fa-pen-to-square"
        description="Kaydedilince cari satırları yeni değerlerle yeniden yazılır."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Nakliye' => null, 'Seferler' => route('admin.shipments.index'), 'Düzenle' => null]"
        :back="route('admin.shipments.index')"
    >
        <form method="POST" action="{{ route('admin.shipments.update', $shipment) }}" data-shipment-entry
              data-vehicles-url="{{ route('admin.shipments.lookups.vehicles') }}" data-addresses-url="{{ route('admin.shipments.lookups.addresses') }}">
            @csrf
            @method('PUT')
            <x-form.grid :model="$shipment">
                @include('admin.shipments.partials.fields')
                <x-form.actions label="Güncelle" :cancel="route('admin.shipments.index')" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/shipments.js')
@endpush
