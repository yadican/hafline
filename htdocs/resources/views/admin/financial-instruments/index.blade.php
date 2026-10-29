@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Çek / Senet"
        icon="fa-solid fa-money-check"
        description="Alınan ve verilen çek / senetler: giriş, vade takibi, tahsil, ciro, ödeme ve karşılıksız işlemleri."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Çek / Senet' => null]"
    >
        @include('admin.financial-instruments.partials.index.toolbar')
        <div class="panel-ledger-totals">
            <span>Portföydeki alınan ({{ $totals['received_count'] }}) <x-ui.money :value="$totals['received']" /></span>
            <span>Ödenecek verilen ({{ $totals['given_count'] }}) <x-ui.money :value="$totals['given']" /></span>
            @if ($totals['overdue_count'] > 0)
                <a href="{{ route('admin.financial-instruments.index', [...$filters, 'overdue' => '1']) }}">Vadesi geçmiş: {{ $totals['overdue_count'] }}</a>
            @endif
        </div>
        <x-table.list>
            @include('admin.financial-instruments.partials.index.table')
        </x-table.list>
        <x-table.pager :items="$instruments" />
    </x-panel.page>
@endsection
