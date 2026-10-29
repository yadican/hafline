@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni yakıt yeri"
        icon="fa-solid fa-plus"
        description="Yakıt fişlerinde seçilecek bir istasyon ya da saha pompası ekleyin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Yakıt' => null, 'Yakıt yerleri' => route('admin.fuel-stations.index'), 'Yeni' => null]"
        :back="route('admin.fuel-stations.index')"
    >
        @include('admin.fuel-stations.partials.create.form')
    </x-panel.page>
@endsection
