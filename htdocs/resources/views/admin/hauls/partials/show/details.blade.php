<div class="panel-form-grid">
    <x-form.group legend="Fiş" icon="fa-solid fa-file-lines" description="Fiş numarası, araç, tarih ve vardiya">
        <x-form.field label="Fiş No">
            <div>{{ $haul->ticket_no }}</div>
        </x-form.field>
        <x-form.field label="Plaka">
            <div>
                @if ($haul->vehicle && ! $haul->vehicle->trashed())
                    <a href="{{ route('admin.vehicles.show', $haul->vehicle) }}" class="panel-plate-text">{{ $haul->vehicle->plate }}</a>
                @else
                    <span class="panel-plate-text">{{ $haul->vehicle?->plate ?: '—' }}</span>
                @endif
            </div>
        </x-form.field>
        <x-form.field label="Sınıf">
            <div>{{ $haul->vehicle?->vehicleClass?->name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Şoför">
            <div>{{ $haul->driver_name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Tarih / saat">
            <div>{{ $haul->hauled_at->format('d.m.Y H:i') }}</div>
        </x-form.field>
        <x-form.field label="Vardiya">
            <div><x-ui.enum-badge :value="$haul->shift" text /></div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="Güzergah" icon="fa-solid fa-route" description="Şantiye, döküm yeri, fiyat ve tonaj">
        <x-form.field label="Şantiye">
            <div>{{ $haul->site?->name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Döküm yeri">
            <div>{{ $haul->dumpSite?->name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Nakliye ücreti">
            <div><x-ui.money :value="$haul->price" /></div>
        </x-form.field>
        <x-form.field label="Döküm ücreti">
            <div><x-ui.money :value="$haul->dump_price" /></div>
        </x-form.field>
        <x-form.field label="Tonaj">
            <div>{{ $haul->tonnage ?: '—' }}</div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="Cari" icon="fa-solid fa-scale-balanced" description="Fişin yazıldığı cari hesaplar">
        <x-form.field label="Araç sahibi">
            <div>{{ $haul->party?->name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Döküm işletmecisi">
            <div>{{ $haul->dumpParty?->name ?: '—' }}</div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="Kayıt" icon="fa-solid fa-clock-rotate-left" description="Fişi giren kullanıcı ve kayıt zamanı">
        <x-form.field label="Giren">
            <div>{{ $haul->creator?->name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Kayıt zamanı">
            <div>{{ $haul->created_at?->format('d.m.Y H:i') ?: '—' }}</div>
        </x-form.field>
    </x-form.group>

    @if (! empty($photoUrl))
        <x-form.group legend="Saha fotoğrafı" icon="fa-solid fa-camera" description="Plaka taramasından gelen görüntü" class="is-wide">
            <div class="md:col-span-full">
                <a href="{{ $photoUrl }}" target="_blank" rel="noopener" class="inline-block">
                    <img
                        src="{{ $photoUrl }}"
                        alt="Saha fotoğrafı"
                        class="max-h-64 max-w-full rounded border border-[var(--ui-border-soft)] object-contain"
                    >
                </a>
            </div>
        </x-form.group>
    @endif
</div>
