<div class="panel-form-grid is-detail">
    <x-form.group :legend="$instrument->payment_method->label()" :icon="$instrument->payment_method->icon()" description="Tutar, vade ve kâğıt üzerindeki bilgiler">
        <x-form.field label="Durum">
            <div><x-ui.enum-badge :value="$instrument->status" text /></div>
        </x-form.field>
        <x-form.field label="Yön">
            <div>{{ $instrument->direction->label() }}</div>
        </x-form.field>
        <x-form.field label="Tutar">
            <div><strong><x-ui.money :value="$instrument->amount" /></strong></div>
        </x-form.field>
        <x-form.field label="Vade">
            <div>
                {{ $instrument->due_on->format('d.m.Y') }}
                @if ($instrument->isOverdue())
                    <span class="panel-chip"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Vadesi geçti</span>
                @endif
            </div>
        </x-form.field>
        <x-form.field label="No">
            <div>{{ $instrument->number ?: '—' }}</div>
        </x-form.field>
        @if ($instrument->payment_method === \App\Enums\PaymentMethod::Cheque)
            <x-form.field label="Çekin bankası">
                <div>{{ $instrument->bank_name ?: '—' }}</div>
            </x-form.field>
        @endif
        <x-form.field label="Keşideci">
            <div>{{ $instrument->drawer ?: '—' }}</div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="Cari" icon="fa-solid fa-address-card" description="Çeki / senedi veren ya da alan cari">
        <x-form.field label="Cari">
            <div>
                @if ($instrument->party)
                    <a href="{{ route('admin.parties.show', $instrument->party) }}">{{ $instrument->party->name }}</a>
                @else
                    —
                @endif
            </div>
        </x-form.field>
        <x-form.field label="İşlem tarihi">
            <div>{{ $instrument->issued_on->format('d.m.Y') }}</div>
        </x-form.field>
        <x-form.field label="Açıklama">
            <div>{{ $instrument->description ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Cari etkisi">
            <div>{{ $instrument->direction === \App\Enums\InstrumentDirection::Received ? 'Cari, çek / senet alındığında alacaklandı' : 'Cari, çek / senet verildiğinde borçlandırıldı' }}</div>
        </x-form.field>
    </x-form.group>
</div>
