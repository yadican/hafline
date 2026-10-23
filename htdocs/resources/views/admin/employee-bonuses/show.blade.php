@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Personel primi detayı"
        icon="fa-solid fa-gift"
        :description="$bonus->employee?->name"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Personel' => null, 'Prim girişi' => route('admin.employee-bonuses.index'), 'Detay' => null]"
        :back="route('admin.employee-bonuses.index')"
    >
        <x-slot:actions>
            @can('edit-employee-bonus')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.employee-bonuses.edit', $bonus)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        <div class="panel-form-grid">
            <x-form.group legend="Prim" icon="fa-solid fa-gift" description="Personel, tutar ve tarih">
                <x-form.field label="Personel">
                    <div>
                        @if ($bonus->employee && ! $bonus->employee->trashed())
                            <a href="{{ route('admin.employees.show', $bonus->employee) }}">{{ $bonus->employee->name }}</a>
                        @else
                            {{ $bonus->employee?->name ?: '—' }}
                        @endif
                    </div>
                </x-form.field>
                <x-form.field label="Prim">
                    <div><x-ui.money :value="$bonus->amount" /></div>
                </x-form.field>
                <x-form.field label="Tarih">
                    <div>{{ $bonus->occurred_on?->format('d.m.Y') ?: '—' }}</div>
                </x-form.field>
                <x-form.field label="Açıklama">
                    <div>{{ $bonus->description ?: '—' }}</div>
                </x-form.field>
            </x-form.group>

            <x-form.group legend="Kayıt" icon="fa-solid fa-clock-rotate-left" description="Primi giren kullanıcı ve kayıt zamanı">
                <x-form.field label="Giren">
                    <div>{{ $bonus->creator?->name ?: '—' }}</div>
                </x-form.field>
                <x-form.field label="Kayıt zamanı">
                    <div>{{ $bonus->created_at?->format('d.m.Y H:i') ?: '—' }}</div>
                </x-form.field>
            </x-form.group>
        </div>
    </x-panel.page>
@endsection
