@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Kullanıcıyı düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$user->name.' bilgilerini güncelleyin.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Yetki yönetimi' => null, 'Kullanıcılar' => route('admin.users.index'), 'Düzenle' => null]"
        :back="route('admin.users.index')"
    >
        @include('admin.users.partials.edit.form')
    </x-panel.page>
@endsection
