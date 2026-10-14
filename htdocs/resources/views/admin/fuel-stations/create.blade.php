@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni akaryakıt istasyonu"
        icon="fa-solid fa-plus"
        description="Yakıt fişlerinde seçilecek yeni bir istasyon ekleyin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Saha' => null, 'Akaryakıt istasyonları' => route('admin.fuel-stations.index'), 'Yeni' => null]"
        :back="route('admin.fuel-stations.index')"
    >
        @include('admin.fuel-stations.partials.create.form')
    </x-panel.page>
@endsection
