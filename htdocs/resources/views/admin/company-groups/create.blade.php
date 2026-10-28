@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni şirket grubu"
        icon="fa-solid fa-plus"
        description="Birlikte izlenecek şirketler için yeni bir grup ekleyin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Cari' => null, 'Şirket grupları' => route('admin.company-groups.index'), 'Yeni' => null]"
        :back="route('admin.company-groups.index')"
    >
        <form method="POST" action="{{ route('admin.company-groups.store') }}">
            @csrf
            <x-form.grid>
                @include('admin.company-groups.partials.form.fields')
                <x-form.actions label="Kaydet" :cancel="route('admin.company-groups.index')" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection
