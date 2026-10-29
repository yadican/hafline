@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Cari kartı düzenle"
        :icon="$party->type->icon()"
        :description="$party->name.' · '.$party->type->label()"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Cari' => null, $party->type->pluralLabel() => route($party->type->indexRoute()), 'Düzenle' => null]"
        :back="route($party->type->indexRoute())"
    >
        <form method="POST" action="{{ route('admin.parties.update', $party) }}">
            @csrf
            @method('PUT')
            <x-form.grid :model="$values">
                @include('admin.parties.partials.form.fields', ['type' => $party->type])
                <x-form.actions label="Güncelle" :cancel="route($party->type->indexRoute())" />
            </x-form.grid>
        </form>

        @include('admin.parties.partials.edit.sites')
    </x-panel.page>
@endsection
