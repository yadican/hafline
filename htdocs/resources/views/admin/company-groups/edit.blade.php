@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Şirket grubunu düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$group->name.' grubunun bilgilerini güncelleyin.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Cari' => null, 'Şirket grupları' => route('admin.company-groups.index'), 'Düzenle' => null]"
        :back="route('admin.company-groups.index')"
    >
        <form method="POST" action="{{ route('admin.company-groups.update', $group) }}">
            @csrf
            @method('PUT')
            <x-form.grid :model="$group">
                @include('admin.company-groups.partials.form.fields')
                <x-form.actions label="Güncelle" :cancel="route('admin.company-groups.index')" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection
