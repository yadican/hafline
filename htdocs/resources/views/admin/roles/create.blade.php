@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni rol"
        icon="fa-solid fa-plus"
        description="Rol adı ve izinlerini tanımlayın."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Yetki yönetimi' => null, 'Roller' => route('admin.roles.index'), 'Yeni' => null]"
        :back="route('admin.roles.index')"
    >
        @include('admin.roles.partials.create.form')
    </x-panel.page>
@endsection
