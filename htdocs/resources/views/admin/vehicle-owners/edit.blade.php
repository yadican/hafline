@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Araç sahibini düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$vehicleOwner->full_name.' bilgilerini güncelleyin.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Filo' => null, 'Araç sahipleri' => route('admin.vehicle-owners.index'), 'Düzenle' => null]"
        :back="route('admin.vehicle-owners.index')"
    >
        @include('admin.vehicle-owners.partials.edit.form')
    </x-panel.page>
@endsection
