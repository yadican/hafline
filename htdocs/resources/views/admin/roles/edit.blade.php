@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Rolü düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$role->name.' izinlerini güncelleyin.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Yetki yönetimi' => null, 'Roller' => route('admin.roles.index'), 'Düzenle' => null]"
        :back="route('admin.roles.index')"
    >
        @include('admin.roles.partials.edit.form')
    </x-panel.page>
@endsection
