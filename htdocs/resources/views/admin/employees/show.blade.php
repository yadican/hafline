@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Personel kartı detayı"
        icon="fa-solid fa-id-badge"
        :description="$employee->name"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Personel' => null, 'Personel kartları' => route('admin.employees.index'), 'Detay' => null]"
        :back="route('admin.employees.index')"
    >
        <x-slot:actions>
            @can('edit-employee')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.employees.edit', $employee)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        @include('admin.employees.partials.show.details')
        @include('admin.employees.partials.edit.periods')
    </x-panel.page>
@endsection
