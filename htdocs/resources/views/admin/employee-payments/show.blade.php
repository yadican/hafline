@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Personel ödemesi detayı"
        icon="fa-solid fa-money-bill-transfer"
        :description="$payment->employee?->name"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Personel' => null, 'Ödeme girişi' => route('admin.employee-payments.index'), 'Detay' => null]"
        :back="route('admin.employee-payments.index')"
    >
        <x-slot:actions>
            @can('edit-employee-payment')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.employee-payments.edit', $payment)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        <div class="panel-form-grid is-detail">
            <x-form.group legend="Ödeme" icon="fa-solid fa-money-bill-transfer" description="Personel, tutar ve dönem">
                <x-form.field label="Personel">
                    <div>
                        @if ($payment->employee && ! $payment->employee->trashed())
                            <a href="{{ route('admin.employees.show', $payment->employee) }}">{{ $payment->employee->name }}</a>
                        @else
                            {{ $payment->employee?->name ?: '—' }}
                        @endif
                    </div>
                </x-form.field>
                <x-form.field label="Tutar">
                    <div><x-ui.money :value="$payment->amount" /></div>
                </x-form.field>
                <x-form.field label="Ödeme tipi">
                    <div>{{ $payment->payment_method?->label() ?: '—' }}</div>
                </x-form.field>
                <x-form.field label="Dönem">
                    <div>{{ $payment->period?->translatedFormat('F Y') ?: '—' }}</div>
                </x-form.field>
                <x-form.field label="Tarih">
                    <div>{{ $payment->occurred_on?->format('d.m.Y') ?: '—' }}</div>
                </x-form.field>
                <x-form.field label="Açıklama">
                    <div>{{ $payment->description ?: '—' }}</div>
                </x-form.field>
            </x-form.group>

            <x-form.group legend="Kayıt" icon="fa-solid fa-clock-rotate-left" description="Ödemeyi giren kullanıcı ve kayıt zamanı">
                <x-form.field label="Giren">
                    <div>{{ $payment->creator?->name ?: '—' }}</div>
                </x-form.field>
                <x-form.field label="Kayıt zamanı">
                    <div>{{ $payment->created_at?->format('d.m.Y H:i') ?: '—' }}</div>
                </x-form.field>
            </x-form.group>
        </div>
    </x-panel.page>
@endsection
