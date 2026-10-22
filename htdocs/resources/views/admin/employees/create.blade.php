@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni personel kartı"
        icon="fa-solid fa-plus"
        description="Mevcut bir kişi cari kartına maaş ve iş dönemi bilgisi bağlayın."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Personel' => null, 'Personel kartları' => route('admin.employees.index'), 'Yeni' => null]"
        :back="route('admin.employees.index')"
    >
        @include('admin.employees.partials.create.form')
    </x-panel.page>
@endsection
