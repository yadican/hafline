<div class="panel-form-grid is-detail">
    <x-form.group legend="Fiş" icon="fa-solid fa-file-lines" description="Fiş numarası, araç ve tarih">
        <x-form.field label="Fiş No">
            <div>{{ $receipt->ticket_no ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Plaka">
            <div>
                @if ($receipt->vehicle && ! $receipt->vehicle->trashed())
                    <a href="{{ route('admin.vehicles.show', $receipt->vehicle) }}" class="panel-plate-text">{{ $receipt->vehicle->plate }}</a>
                @else
                    <span class="panel-plate-text">{{ $receipt->vehicle?->plate ?: '—' }}</span>
                @endif
            </div>
        </x-form.field>
        <x-form.field label="Araç sahibi">
            <div>{{ $receipt->party?->name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Tarih">
            <div>{{ $receipt->issued_on->format('d.m.Y') }}</div>
        </x-form.field>
        <x-form.field label="Vardiya">
            <div><x-ui.enum-badge :value="$receipt->shift" text /></div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="Yakıt" icon="fa-solid fa-gas-pump" description="Yakıt kaynağı, litre, alış ve satış">
        <x-form.field label="Yakıt kaynağı">
            <div>{{ $receipt->fuelStation?->name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Şantiye">
            <div>{{ $receipt->site?->name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Litre">
            <div>{{ \App\Models\FuelReceipt::formatQuantity($receipt->quantity) }}</div>
        </x-form.field>
        <x-form.field label="Satış fiyatı">
            <div><x-ui.money :value="$receipt->unit_price" :decimals="4" suffix="TL/lt" /></div>
        </x-form.field>
        <x-form.field label="Araç sahibine borç">
            <div><x-ui.money :value="$receipt->amount" /></div>
        </x-form.field>
        @if ($receipt->fuel_party_id)
            <x-form.field label="Alış fiyatı">
                <div><x-ui.money :value="$receipt->purchase_price" :decimals="4" suffix="TL/lt" /></div>
            </x-form.field>
            <x-form.field label="İstasyona alacak">
                <div><x-ui.money :value="$receipt->purchase_amount" /> · {{ $receipt->fuelParty?->name }}</div>
            </x-form.field>
        @else
            <x-form.field label="Alış">
                <div>Kendi depomuzdan; stoktan düşer</div>
            </x-form.field>
        @endif
    </x-form.group>

    <x-form.group legend="Ek bilgi" icon="fa-solid fa-circle-info" description="Kilometre, alan kişi ve taşıma fiş adedi">
        <x-form.field label="Kilometre">
            <div>{{ $receipt->km !== null ? number_format($receipt->km, 0, ',', '.') : '—' }}</div>
        </x-form.field>
        <x-form.field label="Alan kişi">
            <div>{{ $receipt->received_by ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Taşıma fiş adedi">
            <div>{{ $receipt->haul_ticket_count ?? '—' }}</div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="Kayıt" icon="fa-solid fa-clock-rotate-left" description="Fişi giren kullanıcı ve kayıt zamanı">
        <x-form.field label="Giren">
            <div>{{ $receipt->creator?->name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Kayıt zamanı">
            <div>{{ $receipt->created_at?->format('d.m.Y H:i') ?: '—' }}</div>
        </x-form.field>
    </x-form.group>
</div>
