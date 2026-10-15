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
        @include('admin.vehicles.partials.edit.ownerships')
        @if ($vehicle->isHourly() || $vehicle->rates->isNotEmpty())
            @include('admin.vehicles.partials.edit.rates')
        @endif
    </x-panel.page>
@endsection
