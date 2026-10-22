@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni personel primi"
        icon="fa-solid fa-plus"
        description="Personele verilen primi kaydedin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Personel' => null, 'Prim girişi' => route('admin.employee-bonuses.index'), 'Yeni' => null]"
        :back="route('admin.employee-bonuses.index')"
    >
        @include('admin.employee-bonuses.partials.create.form')
    </x-panel.page>
@endsection
