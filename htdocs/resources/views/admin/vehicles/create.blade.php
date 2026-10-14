@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni araç"
        icon="fa-solid fa-plus"
        description="Taşıma fişlerinde plakasıyla seçilecek yeni bir araç ekleyin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Filo' => null, 'Araçlar' => route('admin.vehicles.index'), 'Yeni' => null]"
        :back="route('admin.vehicles.index')"
    >
        @include('admin.vehicles.partials.create.form')
    </x-panel.page>
@endsection
