@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Araç sınıfını düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$vehicleClass->name.' bilgilerini güncelleyin.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Filo' => null, 'Araç sınıfları' => route('admin.vehicle-classes.index'), 'Düzenle' => null]"
        :back="route('admin.vehicle-classes.index')"
    >
        @include('admin.vehicle-classes.partials.edit.form')
    </x-panel.page>
@endsection
