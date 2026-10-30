@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Faturayı düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$invoice->isDraft() ? 'Taslak düzenlenir; cariye yazılmaz. Onayla ile cariye geçer.' : 'Kaydedilince cari satırı yeni toplamla yeniden yazılır.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Faturalar' => route('admin.invoices.index'), 'Düzenle' => null]"
        :back="route('admin.invoices.index')"
    >
        <form method="POST" action="{{ route('admin.invoices.update', $invoice) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <x-form.grid :model="$invoice">
                @include('admin.invoices.partials.fields')
                <x-form.actions :label="$invoice->isDraft() ? 'Taslağı güncelle' : 'Güncelle'" :cancel="route('admin.invoices.index')" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection
