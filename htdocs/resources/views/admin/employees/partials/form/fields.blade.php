<x-form.group legend="Personel" icon="fa-solid fa-id-badge" description="Kişi, görev, maaş ve hesap" class="is-wide">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @isset($employee)
            <x-form.field label="Kişi">
                <div class="flex min-h-[var(--ui-control-h)] items-center">
                    <p class="panel-hint">
                        <strong>{{ $employee->name }}</strong>
                        —
                        <a href="{{ route('admin.parties.edit', $employee->party) }}" class="underline">Cari kartı düzenle</a>
                    </p>
                </div>
            </x-form.field>
        @else
            <x-form.select searchable name="party_id" label="Kişi" :options="$parties" placeholder="Kişi seçin" required autofocus>
                <p class="panel-hint">
                    Kişi listede yoksa önce
                    <a href="{{ route('admin.parties.create', ['type' => 'person', 'role' => 'employee']) }}" class="underline">Cari → Kişiler</a>
                    üzerinden ekleyin.
                </p>
            </x-form.select>
        @endisset
        <x-form.select searchable hide-search name="occupation" label="Görev" :options="\App\Enums\EmployeeOccupation::options()" placeholder="Seçiniz" :autofocus="isset($employee)" />
        <x-form.input name="started_on" label="İşe başlama" type="date" />
        <x-form.input name="salary" label="Maaş" type="number" step="0.01" min="0" required />
        <x-form.input name="bank_account_no" label="Hesap no / IBAN" maxlength="100" />
        <x-form.checkbox name="active" label="Durum" text="Personel listelerinde görünsün" :checked="true" />
    </div>
</x-form.group>
