<x-form.group legend="Prim" icon="fa-solid fa-gift" description="Personel, tutar ve tarih">
    <x-form.select searchable name="employee_id" label="Personel" :options="$options['employees']" required autofocus data-employee-balance />
    <div class="panel-cari-summary is-idle" data-employee-balance-status aria-live="polite">Personel seçilince maaş / prim / ödeme bakiyesi gelir</div>
    <x-form.input name="amount" label="Prim" type="number" step="0.01" min="0.01" required />
    <x-form.input name="occurred_on" label="Tarih" type="date" :value="old('occurred_on', isset($bonus) ? $bonus->occurred_on?->toDateString() : now()->toDateString())" required />
    <x-form.textarea name="description" label="Açıklama" rows="2" maxlength="255" />
</x-form.group>
