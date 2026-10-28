@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yakıt kaynağını düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$fuelStation->name.' bilgilerini güncelleyin.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Yakıt' => null, 'Yakıt kaynakları' => route('admin.fuel-stations.index'), 'Düzenle' => null]"
        :back="route('admin.fuel-stations.index')"
    >
        @include('admin.fuel-stations.partials.edit.form')
    </x-panel.page>
@endsection
