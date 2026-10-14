@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni araç sınıfı"
        icon="fa-solid fa-plus"
        description="Araçlara atanacak ve fiyatlandırmada kullanılacak yeni bir sınıf ekleyin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Filo' => null, 'Araç sınıfları' => route('admin.vehicle-classes.index'), 'Yeni' => null]"
        :back="route('admin.vehicle-classes.index')"
    >
        @include('admin.vehicle-classes.partials.create.form')
    </x-panel.page>
@endsection
