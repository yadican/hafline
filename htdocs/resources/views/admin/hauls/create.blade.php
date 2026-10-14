@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni taşıma fişi"
        icon="fa-solid fa-plus"
        description="Aynı fiş için birden fazla sefer girerseniz her sefer ayrı kayıt olur."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Taşıma fişleri' => route('admin.hauls.index'), 'Yeni fiş' => null]"
        :back="route('admin.hauls.index')"
    >
        @include('admin.hauls.partials.create.form')
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/hauls.js')
@endpush
