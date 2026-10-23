<div class="panel-form-grid">
    <x-form.group legend="Fiş" icon="fa-solid fa-file-lines" description="Fiş numarası, makine, tarih ve vardiya">
        <x-form.field label="Fiş No">
            <div>{{ $work->ticket_no }}</div>
        </x-form.field>
        <x-form.field label="Makine plakası">
            <div>
                @if ($work->vehicle && ! $work->vehicle->trashed())
                    <a href="{{ route('admin.vehicles.show', $work->vehicle) }}" class="panel-plate-text">{{ $work->vehicle->plate }}</a>
                @else
                    <span class="panel-plate-text">{{ $work->vehicle?->plate ?: '—' }}</span>
                @endif
            </div>
        </x-form.field>
        <x-form.field label="Makine sahibi">
            <div>{{ $work->party?->name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Tarih">
            <div>{{ $work->worked_on->format('d.m.Y') }}</div>
        </x-form.field>
        <x-form.field label="Vardiya">
            <div><x-ui.enum-badge :value="$work->shift" text /></div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="Çalışma" icon="fa-solid fa-person-digging" description="Şantiye, operatör, çalışılan saat ve saat ücreti">
        <x-form.field label="Şantiye">
            <div>{{ $work->site?->name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Çalışılan saat">
            <div>{{ \App\Models\MachineWork::formatHours($work->hours) }}</div>
        </x-form.field>
        <x-form.field label="Saat ücreti">
            <div><x-ui.money :value="$work->hourly_rate" /></div>
        </x-form.field>
        <x-form.field label="Tutar">
            <div><x-ui.money :value="$work->amount" /></div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="Operatör" icon="fa-solid fa-user-gear" description="Operatöre yazılan alacak">
        <x-form.field label="Operatör">
            <div>{{ $work->operator?->name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Operatör saat ücreti">
            <div><x-ui.money :value="$work->operator_rate" /></div>
        </x-form.field>
        <x-form.field label="Operatöre alacak">
            <div><x-ui.money :value="$work->operator_amount" /></div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="Kayıt" icon="fa-solid fa-clock-rotate-left" description="Fişi giren kullanıcı ve kayıt zamanı">
        <x-form.field label="Giren">
            <div>{{ $work->creator?->name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Kayıt zamanı">
            <div>{{ $work->created_at?->format('d.m.Y H:i') ?: '—' }}</div>
        </x-form.field>
    </x-form.group>
</div>
