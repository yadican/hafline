@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni stok kalemi"
        icon="fa-solid fa-plus"
        description="Fatura satırlarında ve stok hareketlerinde seçilecek yeni bir kalem ekleyin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Stok' => route('admin.stock-items.index'), 'Yeni' => null]"
        :back="route('admin.stock-items.index')"
    >
        @include('admin.stock-items.partials.create.form')
    </x-panel.page>
@endsection
