@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni kullanıcı"
        icon="fa-solid fa-plus"
        description="Yönetim paneline giriş yapacak yeni kullanıcı ekleyin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Yetki yönetimi' => null, 'Kullanıcılar' => route('admin.users.index'), 'Yeni' => null]"
        :back="route('admin.users.index')"
    >
        @include('admin.users.partials.create.form')
    </x-panel.page>
@endsection
