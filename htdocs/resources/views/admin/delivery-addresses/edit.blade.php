@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Sevk yerini düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$address->name.' bilgilerini ve satış fiyatlarını güncelleyin.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Nakliye' => null, 'Sevk yerleri' => route('admin.delivery-addresses.index'), 'Düzenle' => null]"
        :back="route('admin.delivery-addresses.index')"
    >
        <form method="POST" action="{{ route('admin.delivery-addresses.update', $address) }}">
            @csrf
            @method('PUT')
            <x-form.grid :model="$address">
                @include('admin.delivery-addresses.partials.form')
                <x-form.actions label="Güncelle" :cancel="route('admin.delivery-addresses.index')" />
            </x-form.grid>
        </form>

        @include('admin.transport-prices.list', [
            'kind' => \App\Enums\TransportPriceKind::Sale,
            'legend' => 'Müşteri satış fiyatları',
            'description' => 'Bu sevk yerine giden malzemenin birim başına müşteriye yazılan fiyatı (KDV hariç)',
            'keyLabel' => 'Malzeme',
            'keyName' => 'material_id',
            'keyOptions' => $materials,
            'keyOf' => fn ($price) => $price->material?->name,
            'hidden' => ['delivery_address_id' => $address->id],
        ])
    </x-panel.page>
@endsection
