@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Ocak / yükleme yerini düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$quarry->name.' bilgilerini ve fiyat listelerini güncelleyin.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Nakliye' => null, 'Ocaklar ve yükleme yerleri' => route('admin.quarries.index'), 'Düzenle' => null]"
        :back="route('admin.quarries.index')"
    >
        <form method="POST" action="{{ route('admin.quarries.update', $quarry) }}">
            @csrf
            @method('PUT')
            <x-form.grid :model="$quarry">
                @include('admin.quarries.partials.form')
                <x-form.actions label="Güncelle" :cancel="route('admin.quarries.index')" />
            </x-form.grid>
        </form>

        @include('admin.transport-prices.list', [
            'prices' => $prices->where('kind', \App\Enums\TransportPriceKind::Quarry),
            'kind' => \App\Enums\TransportPriceKind::Quarry,
            'legend' => 'Ocak alış fiyatları',
            'description' => 'Malzeme satışlı seferde ocağa yazılan birim fiyat',
            'keyLabel' => 'Malzeme',
            'keyName' => 'material_id',
            'keyOptions' => $materials,
            'keyOf' => fn ($price) => $price->material?->name,
            'hidden' => ['quarry_id' => $quarry->id],
        ])

        @include('admin.transport-prices.list', [
            'prices' => $prices->where('kind', \App\Enums\TransportPriceKind::Freight),
            'kind' => \App\Enums\TransportPriceKind::Freight,
            'legend' => 'Nakliyeci fiyatları',
            'description' => 'Buradan sevk yerine taşınan birim başına nakliyeciye yazılan fiyat',
            'keyLabel' => 'Sevk yeri',
            'keyName' => 'delivery_address_id',
            'keyOptions' => $addresses,
            'keyOf' => fn ($price) => $price->deliveryAddress?->party?->name.' · '.$price->deliveryAddress?->name,
            'hidden' => ['quarry_id' => $quarry->id],
        ])
    </x-panel.page>
@endsection
