@extends('admin.layouts.app')

@section('content')
    @php
        $group = $group ?? \App\Enums\CariEntryGroup::tryFrom($filters['group'] ?? '');
        $transfers = ($filters['kind'] ?? null) === 'account_transfer'
            || $group === \App\Enums\CariEntryGroup::AccountTransfer;
        $heading = $heading ?? ($transfers ? 'Hesaplar arası virman' : 'Cari hareketleri');
    @endphp
    <x-panel.page
        :heading="$heading"
        icon="fa-solid fa-right-left"
        :description="$transfers
            ? 'Kasa, banka, POS ve kredi kartı hesaplarımız arasındaki para aktarımları. Cariyi etkilemez; çıkan hesaptan düşer, giren hesaba eklenir.'
            : 'Satıcı, alıcı, petrol, piyasa, plaka, araç sahibi, döküm yeri, şantiye ve kasa girişlerini tek listeden yönetin.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Cari işlemleri' => null, $heading => null]"
    >
        @include('admin.cari-entries.partials.index.toolbar', ['group' => $group])
        <x-table.list>
            @include('admin.cari-entries.partials.index.table')
        </x-table.list>
        <x-table.pager :items="$entries" />
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/cari-entries.js')
@endpush
