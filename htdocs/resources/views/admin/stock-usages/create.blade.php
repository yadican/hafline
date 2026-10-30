@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni stok kullanımı"
        icon="fa-solid fa-plus"
        description="Kalemi şantiye veya kendi aracımız için stoktan düşürün; maliyet gider yazılır."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Stok kullanımı' => route('admin.stock-usages.index'), 'Yeni' => null]"
        :back="route('admin.stock-usages.index')"
    >
        @include('admin.stock-usages.partials.create.form')
    </x-panel.page>
@endsection
