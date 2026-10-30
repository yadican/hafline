@extends('admin.layouts.app')

@section('content')
    <x-panel.page heading="Gösterge paneli" icon="fa-solid fa-gauge" description="Operasyon özeti, bugünkü hareketler ve hızlı erişim.">
        <div class="dash">
            <div class="dash-header">
                <div>
                    <p class="dash-kicker">Operasyon özeti</p>
                    <h1 class="dash-title">Bugünün panosu</h1>
                </div>
                <time class="dash-date" datetime="{{ today()->toDateString() }}">{{ $todayLabel }}</time>
            </div>

            @include('admin.dashboard.partials.stats')

            @include('admin.dashboard.partials.finance-reminders')

            @if ($recentHauls !== null || $cashSummary !== null)
                <div class="dash-panels">
                    @include('admin.dashboard.partials.recent-hauls')
                    @include('admin.dashboard.partials.cash-summary')
                </div>
            @endif

            @include('admin.dashboard.partials.shortcuts')
        </div>
    </x-panel.page>
@endsection
