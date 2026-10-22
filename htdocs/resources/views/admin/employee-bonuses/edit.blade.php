@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Personel primini düzenle"
        icon="fa-solid fa-gift"
        :description="$bonus->employee?->name"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Personel' => null, 'Prim girişi' => route('admin.employee-bonuses.index'), 'Düzenle' => null]"
        :back="route('admin.employee-bonuses.index')"
    >
        @include('admin.employee-bonuses.partials.edit.form')
    </x-panel.page>
@endsection
