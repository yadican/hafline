@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni döküm yeri"
        icon="fa-solid fa-plus"
        description="Taşıma fişlerinde ve güzergahlarda seçilecek yeni bir döküm yeri ekleyin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Saha' => null, 'Döküm yerleri' => route('admin.dump-sites.index'), 'Yeni' => null]"
        :back="route('admin.dump-sites.index')"
    >
        @include('admin.dump-sites.partials.create.form')
    </x-panel.page>
@endsection
