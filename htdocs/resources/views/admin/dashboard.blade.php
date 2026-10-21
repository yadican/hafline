@extends('admin.layouts.app')

@section('content')
    <x-panel.page heading="Gösterge paneli" icon="fa-solid fa-gauge" description="Modüllere hızlı erişim ve genel durum.">
        <div class="dash">
            <div class="dash-hero">
                <div>
                    <img class="dash-logo" src="{{ asset('img/logo.png') }}" alt="Hafline">
                    <h1>Operasyon özeti</h1>
                    <p class="dash-sub">Bugünkü fişler, aktif saha ve filo durumu. Modüllere alttaki kısayollardan geçebilirsiniz.</p>
                </div>
                <div class="dash-hero-route" aria-hidden="true"></div>
            </div>

            @include('admin.dashboard.partials.stats')
            @include('admin.dashboard.partials.shortcuts')
        </div>
    </x-panel.page>
@endsection
