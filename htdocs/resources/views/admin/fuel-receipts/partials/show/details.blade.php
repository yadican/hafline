@php
    $fromTank = ! $receipt->fuel_party_id;
@endphp

<div class="panel-form-grid is-detail">
    <x-form.group legend="1. Fiş" icon="fa-solid fa-file-lines" description="Fiş numarası, tarih ve şantiye">
        <x-form.field label="Fiş No">
            <div>{{ $receipt->ticket_no ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Tarih">
            <div>{{ $receipt->issued_on->format('d.m.Y') }}</div>
        </x-form.field>
        <x-form.field label="Vardiya">
            <div><x-ui.enum-badge :value="$receipt->shift" text /></div>
        </x-form.field>
        <x-form.field label="Şantiye">
            <div>{{ $receipt->site?->name ?: '—' }}</div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="2. Veren: Yakıt yeri" icon="fa-solid fa-gas-pump" description="Yakıtın alındığı yer, litre ve alış fiyatı">
        <x-form.field label="Yakıt yeri">
            <div>{{ $receipt->fuelStation?->name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Litre">
            <div>{{ \App\Models\FuelReceipt::formatQuantity($receipt->quantity) }}</div>
        </x-form.field>
        @unless ($fromTank)
            <x-form.field label="Akaryakıt firması">
                <div>{{ $receipt->fuelParty?->name ?: '—' }}</div>
            </x-form.field>
            <x-form.field label="Alış fiyatı">
                <div><x-ui.money :value="$receipt->purchase_price" :decimals="4" suffix="TL/lt" /></div>
            </x-form.field>
        @endunless
        <div class="mt-3 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm">
            <div class="flex justify-between gap-3 font-bold text-red-700">
                <span>{{ $fromTank ? 'SAHA POMPASI:' : 'İSTASYONA ALACAK:' }}</span>
                @if ($fromTank)
                    <span>{{ \App\Models\FuelReceipt::formatQuantity($receipt->quantity) }} lt stoktan düştü</span>
                @else
                    <x-ui.money :value="$receipt->purchase_amount" />
                @endif
            </div>
        </div>
    </x-form.group>

    <x-form.group legend="3. Alan: Araç" icon="fa-solid fa-truck" description="Plaka, araç sahibi ve satış fiyatı">
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
        <x-form.field label="Satış fiyatı">
            <div><x-ui.money :value="$receipt->unit_price" :decimals="4" suffix="TL/lt" /></div>
        </x-form.field>
        <div class="mt-3 rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm">
            <div class="flex justify-between gap-3 font-bold text-green-800">
                <span>ARAÇ SAHİBİNE BORÇ:</span>
                <x-ui.money :value="$receipt->amount" />
            </div>
            @unless ($fromTank)
                <div class="mt-1 flex justify-between gap-3 border-t border-green-200 pt-2 font-bold text-gray-800">
                    <span>FARK (KOMİSYON):</span>
                    <x-ui.money :value="(float) $receipt->amount - (float) $receipt->purchase_amount" />
                </div>
            @endunless
        </div>
    </x-form.group>

    <x-form.group legend="4. Diğer" icon="fa-solid fa-circle-info" description="Kilometre, alan kişi ve taşıma fiş adedi">
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

    <x-form.group legend="Kayıt" icon="fa-solid fa-clock-rotate-left" description="Fişi giren kullanıcı ve kayıt zamanı" class="is-wide">
        <div class="panel-detail-columns">
            <x-form.field label="Giren">
                <div>{{ $receipt->creator?->name ?: '—' }}</div>
            </x-form.field>
            <x-form.field label="Kayıt zamanı">
                <div>{{ $receipt->created_at?->format('d.m.Y H:i') ?: '—' }}</div>
            </x-form.field>
        </div>
    </x-form.group>
</div>
