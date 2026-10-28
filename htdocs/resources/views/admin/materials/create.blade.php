@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni malzeme"
        icon="fa-solid fa-plus"
        description="Seferde seçilecek yeni bir malzeme ekleyin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Nakliye' => null, 'Malzemeler' => route('admin.materials.index'), 'Yeni' => null]"
        :back="route('admin.materials.index')"
    >
        <form method="POST" action="{{ route('admin.materials.store') }}">
            @csrf
            <x-form.grid :model="['unit' => 'ton']">
                @include('admin.materials.partials.form')
                <x-form.actions label="Kaydet" :cancel="route('admin.materials.index')" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection
