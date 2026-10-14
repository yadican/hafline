@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni araç sahibi"
        icon="fa-solid fa-plus"
        description="Araç kayıtlarında seçilecek yeni bir araç sahibi ekleyin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Filo' => null, 'Araç sahipleri' => route('admin.vehicle-owners.index'), 'Yeni' => null]"
        :back="route('admin.vehicle-owners.index')"
    >
        @include('admin.vehicle-owners.partials.create.form')
    </x-panel.page>
@endsection
