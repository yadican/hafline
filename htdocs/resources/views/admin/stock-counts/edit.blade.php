@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Stok sayımını düzenle"
        icon="fa-solid fa-pen-to-square"
        description="Sayılan miktar ve tarihi güncelleyin; fark yeniden hesaplanır."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Stok sayımı' => route('admin.stock-counts.index'), 'Düzenle' => null]"
        :back="route('admin.stock-counts.index')"
    >
        @include('admin.stock-counts.partials.edit.form')
    </x-panel.page>
@endsection
