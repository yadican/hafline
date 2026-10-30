@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Stok kalemini düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$stockItem->name.' kaleminin bilgilerini güncelleyin.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Stok' => route('admin.stock-items.index'), 'Düzenle' => null]"
        :back="route('admin.stock-items.index')"
    >
        @include('admin.stock-items.partials.edit.form')
    </x-panel.page>
@endsection
