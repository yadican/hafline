@extends('admin.layouts.app')

@section('content')
    @php
        $canPay = ! $invoice->isDraft()
            && $invoice->direction->acceptsPayment()
            && $invoice->remainingAmount() > 0
            && auth()->user()?->can('edit-invoice')
            && auth()->user()?->can('create-cari-entry');
    @endphp
    <x-panel.page
        heading="Fatura detayı"
        icon="fa-solid fa-file-invoice"
        :description="$invoice->direction->label().' faturası '.$invoice->invoice_no.' · '.$invoice->issued_on->format('d.m.Y')"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Faturalar' => route('admin.invoices.index'), 'Detay' => null]"
        :back="route('admin.invoices.index')"
    >
        <x-slot:actions>
            @if ($invoice->isDraft() && auth()->user()?->can('edit-invoice'))
                <form method="POST" action="{{ route('admin.invoices.approve', $invoice) }}" class="js-confirm" data-confirm-title="Fatura onaylansın mı?" data-confirm-text="Cariye yazılır; bundan sonra ödenebilir." data-confirm-button="Onayla" style="display:inline;">
                    @csrf
                    <x-ui.button type="submit" variant="create" icon="fa-solid fa-check">Onayla</x-ui.button>
                </form>
            @endif
            @if ($canPay)
                <x-ui.button variant="create" icon="fa-solid fa-hand-holding-dollar" x-data x-on:click="$dispatch('invoice-payment-open')">{{ $invoice->direction->payAction() }}</x-ui.button>
            @endif
            @can('edit-invoice')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.invoices.edit', $invoice)">Düzenle</x-ui.button>
            @endcan
            @if ($invoice->paidAmount() <= 0 && auth()->user()?->can('delete-invoice'))
                <form method="POST" action="{{ route('admin.invoices.destroy', $invoice) }}" class="js-confirm" data-confirm-title="Fatura silinsin mi?" data-confirm-text="{{ $invoice->isDraft() ? 'Taslak silinir; cariye yazılmamıştı.' : 'Cariye yazılan satır ters kayıtla kapatılır.' }}" data-confirm-button="Sil" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="danger" icon="fa-solid fa-trash">Sil</x-ui.button>
                </form>
            @endif
        </x-slot:actions>

        @include('admin.invoices.partials.show.details')
        @unless ($invoice->isDraft() || ! $invoice->direction->acceptsPayment())
            @include('admin.invoices.partials.show.payments')
        @endunless
        @include('admin.invoices.partials.show.lines')
        @include('admin.invoices.partials.show.attachments')
    </x-panel.page>
@endsection
