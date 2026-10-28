<x-form.group legend="Sevk yeri" icon="fa-solid fa-location-dot" description="Müşteri ve teslim yeri; müşteri carisine Müşteri rolü verilir">
    <x-form.select name="party_id" label="Müşteri" :options="$parties" placeholder="Cari kart seçin" searchable required />
    <x-form.input name="name" label="Ad" maxlength="255" required />
    <x-form.checkbox name="active" label="Durum" text="Seferde seçilebilsin" checked />
</x-form.group>
