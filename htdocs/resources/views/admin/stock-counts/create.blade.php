@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni stok sayımı"
        icon="fa-solid fa-plus"
        description="Sayılan miktarı girin; fark (sayılan − defter) stoğu düzeltir."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Stok sayımı' => route('admin.stock-counts.index'), 'Yeni' => null]"
        :back="route('admin.stock-counts.index')"
    >
        @include('admin.stock-counts.partials.create.form')
    </x-panel.page>
@endsection
