@extends('admin.layouts.app')

@section('content')
    <x-panel.page heading="Gösterge paneli" icon="fa-solid fa-gauge" description="Modüllere hızlı erişim ve genel durum.">
        @include('admin.dashboard.partials.modules')
    </x-panel.page>
@endsection
