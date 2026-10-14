@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Aracı düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$vehicle->plate.' bilgilerini güncelleyin.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Filo' => null, 'Araçlar' => route('admin.vehicles.index'), 'Düzenle' => null]"
        :back="route('admin.vehicles.index')"
    >
        @include('admin.vehicles.partials.edit.form')
    </x-panel.page>
@endsection
