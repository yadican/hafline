@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Malzemeyi düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$material->name.' bilgilerini güncelleyin.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Nakliye' => null, 'Malzemeler' => route('admin.materials.index'), 'Düzenle' => null]"
        :back="route('admin.materials.index')"
    >
        <form method="POST" action="{{ route('admin.materials.update', $material) }}">
            @csrf
            @method('PUT')
            <x-form.grid :model="$material">
                @include('admin.materials.partials.form')
                <x-form.actions label="Güncelle" :cancel="route('admin.materials.index')" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection
