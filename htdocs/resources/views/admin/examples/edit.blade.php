@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Örnek kaydı düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$example->title.' kaydının bilgilerini güncelleyin.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Örnek kayıtlar' => route('admin.examples.index'), 'Düzenle' => null]"
        :back="route('admin.examples.index')"
    >
        @include('admin.examples.partials.edit.form')
    </x-panel.page>
@endsection
