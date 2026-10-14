@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Güzergahı düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$haulRoute->site?->name.' → '.$haulRoute->dumpSite?->name.' güzergahını ve fiyatlarını güncelleyin.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Saha' => null, 'Güzergahlar' => route('admin.haul-routes.index'), 'Düzenle' => null]"
        :back="route('admin.haul-routes.index')"
    >
        @include('admin.haul-routes.partials.edit.form')
        @include('admin.haul-routes.partials.edit.vehicle-prices')
    </x-panel.page>
@endsection
