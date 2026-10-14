@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Şantiyeyi düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$site->name.' bilgilerini güncelleyin.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Saha' => null, 'Şantiyeler' => route('admin.sites.index'), 'Düzenle' => null]"
        :back="route('admin.sites.index')"
    >
        @include('admin.sites.partials.edit.form')
    </x-panel.page>
@endsection
