@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni örnek kayıt"
        icon="fa-solid fa-plus"
        description="Yeni bir kayıt oluşturmak için formu doldurun."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Örnek kayıtlar' => route('admin.examples.index'), 'Yeni kayıt' => null]"
        :back="route('admin.examples.index')"
    >
        @include('admin.examples.partials.create.form')
    </x-panel.page>
@endsection
