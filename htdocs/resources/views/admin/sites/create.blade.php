@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni şantiye"
        icon="fa-solid fa-plus"
        description="Taşıma fişlerinde ve güzergahlarda seçilecek yeni bir şantiye ekleyin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Saha' => null, 'Şantiyeler' => route('admin.sites.index'), 'Yeni' => null]"
        :back="route('admin.sites.index')"
    >
        @include('admin.sites.partials.create.form')
    </x-panel.page>
@endsection
