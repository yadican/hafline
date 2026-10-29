@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Personel kartını düzenle"
        icon="fa-solid fa-id-badge"
        :description="$employee->name"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Personel' => null, 'Personel kartları' => route('admin.employees.index'), 'Düzenle' => null]"
        :back="route('admin.employees.index')"
    >
        @include('admin.employees.partials.edit.form')
    </x-panel.page>
@endsection
