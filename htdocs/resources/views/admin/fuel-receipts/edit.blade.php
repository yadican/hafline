@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yakıt fişini düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="($receipt->ticket_no ?: 'Bu').' fişi güncelleyin. Alış ve satış fiyatı, yakıt yerini değiştirmedikçe kayıtlı değerinde kalır.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Yakıt fişleri' => route('admin.fuel-receipts.index'), 'Düzenle' => null]"
        :back="route('admin.fuel-receipts.index')"
    >
        <form method="POST" action="{{ route('admin.fuel-receipts.update', $receipt) }}" data-fuel-entry data-station-url="{{ route('admin.fuel-receipts.lookups.station', ['fuelStation' => '__ID__']) }}" data-keep-price>
            @csrf
            @method('PUT')
            <x-form.grid :model="$receipt">
                @include('admin.fuel-receipts.partials.form.fields')
                <x-form.actions label="Güncelle" :cancel="route('admin.fuel-receipts.index')" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/fuel-receipts.js')
@endpush
