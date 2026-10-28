@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni yakıt kaynağı"
        icon="fa-solid fa-plus"
        description="Yakıt fişlerinde seçilecek bir istasyon ya da kendi depomuzu ekleyin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Yakıt' => null, 'Yakıt kaynakları' => route('admin.fuel-stations.index'), 'Yeni' => null]"
        :back="route('admin.fuel-stations.index')"
    >
        @include('admin.fuel-stations.partials.create.form')
    </x-panel.page>
@endsection
