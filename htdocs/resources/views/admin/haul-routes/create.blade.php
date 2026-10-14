@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni güzergah"
        icon="fa-solid fa-plus"
        description="Şantiye ile döküm yeri arasında yeni bir güzergah açın ve sınıf fiyatlarını girin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Saha' => null, 'Güzergahlar' => route('admin.haul-routes.index'), 'Yeni' => null]"
        :back="route('admin.haul-routes.index')"
    >
        @include('admin.haul-routes.partials.create.form')
    </x-panel.page>
@endsection
