@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Stok kullanımını düzenle"
        icon="fa-solid fa-pen-to-square"
        description="Miktar, tarih ve gider yerini güncelleyin; maliyet yeniden hesaplanır."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Stok kullanımı' => route('admin.stock-usages.index'), 'Düzenle' => null]"
        :back="route('admin.stock-usages.index')"
    >
        @include('admin.stock-usages.partials.edit.form')
    </x-panel.page>
@endsection
