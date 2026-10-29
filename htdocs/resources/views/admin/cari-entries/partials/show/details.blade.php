<div class="panel-form-grid is-detail">
    <x-form.group legend="Hareket" icon="fa-solid fa-right-left" description="İşlem türü, yön, tutar ve tarih">
        <x-form.field label="İşlem">
            <div>{{ $kind->label() }}</div>
        </x-form.field>
        <x-form.field label="Yön">
            <div>{{ $details['direction'] }}</div>
        </x-form.field>
        <x-form.field label="Tutar">
            <div><x-ui.money :value="$details['amount']" /></div>
        </x-form.field>
        <x-form.field label="Tarih">
            <div>{{ $details['occurred_on']->format('d.m.Y') }}</div>
        </x-form.field>
        @if ($kind !== \App\Enums\CariEntryKind::Cash)
            <x-form.field label="Ödeme tipi">
                <div>{{ $details['payment_method']?->label() ?: '—' }}</div>
            </x-form.field>
        @endif
        <x-form.field label="Kasa kategorisi">
            <div>{{ $details['category'] ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Açıklama">
            <div>{{ $details['description'] ?: '—' }}</div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="Bağ" icon="fa-solid fa-link" description="İşleme bağlı cari, plaka veya yer">
        @forelse ($details['links'] as $label => $value)
            <x-form.field :label="$label">
                <div>{{ $value }}</div>
            </x-form.field>
        @empty
            <p class="panel-hint">Bu harekete bağlı cari, plaka veya yer yok.</p>
        @endforelse
    </x-form.group>

    @if ($instrument = $details['instrument'])
        <x-form.group :legend="$instrument->payment_method->label()" :icon="$instrument->payment_method->icon()" description="Portföydeki durumu">
            <x-form.field label="Durum">
                <div>
                    <x-ui.enum-badge :value="$instrument->status" text />
                    @can('view-financial-instrument')
                        <a href="{{ route('admin.financial-instruments.show', $instrument) }}">Portföyde aç</a>
                    @endcan
                </div>
            </x-form.field>
            <x-form.field label="Vade">
                <div>{{ $instrument->due_on->format('d.m.Y') }}</div>
            </x-form.field>
            @if ($instrument->bank_name)
                <x-form.field label="Çekin bankası">
                    <div>{{ $instrument->bank_name }}</div>
                </x-form.field>
            @endif
            @if ($instrument->drawer)
                <x-form.field label="Keşideci">
                    <div>{{ $instrument->drawer }}</div>
                </x-form.field>
            @endif
        </x-form.group>
    @endif
</div>
