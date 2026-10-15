<x-form.group legend="Fiş" icon="fa-solid fa-file-lines" description="Fiş numarası, makine, tarih ve vardiya">
    <x-form.input name="ticket_no" label="Fiş No" maxlength="20" required autofocus />
    <x-form.autocomplete
        name="plate"
        label="Makine plakası"
        :url="route('admin.machine-works.lookups.machines')"
        :value="$work?->vehicle?->plate"
        placeholder="34 ABC 123"
        maxlength="20"
        required
        class="panel-plate"
        data-machine-plate
    >
        <x-form.status data-machine-plate-hint>Yazmaya başlayın; yalnızca saatlik çalışan araçlar listelenir</x-form.status>
    </x-form.autocomplete>
    <x-form.input name="worked_on" label="Tarih" type="date" :value="$work ? null : today()" required data-machine-date />
    <x-form.select name="shift" label="Vardiya" :options="$options['shifts']" :value="$work ? null : 'day'" required />
</x-form.group>

<x-form.group legend="Çalışma" icon="fa-solid fa-person-digging" description="Şantiye, operatör, çalışılan saat ve saat ücreti">
    <x-form.select name="site_id" label="Şantiye" placeholder="Şantiye seçin" :options="$options['sites']" required searchable />
    <x-form.select name="operator_id" label="Operatör" placeholder="Operatör yok" :options="$options['operators']" searchable data-machine-operator>
        <x-form.status>
            Seçilen operatör saat × operatör ücreti kadar alacaklı yazılır (personel ise yazılmaz). Ücret yansımasın istiyorsanız boş bırakın ·
            <a href="{{ route('admin.parties.create', ['type' => 'person', 'role' => \App\Enums\PartyRole::MachineOperator->value]) }}" target="_blank">Yeni operatör ekle</a>
        </x-form.status>
    </x-form.select>
    <x-form.money name="operator_rate" label="Operatör saat ücreti" data-machine-operator-rate>
        <x-form.status data-machine-operator-total>Operatör seçilince son fişteki ücreti gelir</x-form.status>
    </x-form.money>
    <x-form.input name="hours" label="Çalışılan saat" type="number" step="0.25" min="0.25" max="24" :value="$work ? null : 1" required data-machine-hours />
    <x-form.money name="hourly_rate" label="Saat ücreti" data-machine-rate>
        <x-form.status data-machine-total>{{ $work
            ? 'Kayıtlı tutar: '.number_format((float) $work->amount, 2, ',', '.').' TL · makine veya tarih değişirse dönem ücreti yeniden gelir'
            : 'Makine ve tarih seçilince dönem ücreti otomatik gelir' }}</x-form.status>
    </x-form.money>
</x-form.group>
