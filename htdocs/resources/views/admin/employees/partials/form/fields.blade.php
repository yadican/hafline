{{-- Create: $parties. Edit: party shown outside. --}}
@if (! isset($employee))
    <x-form.group legend="Kişi" icon="fa-solid fa-user" description="Cari kartındaki kişiye bağlanır">
        <x-form.select searchable name="party_id" label="Kişi" :options="$parties" placeholder="Kişi seçin" required autofocus />
        <p class="panel-hint">
            Kişi listede yoksa önce
            <a href="{{ route('admin.parties.create', ['type' => 'person', 'role' => 'employee']) }}" class="underline">Cari → Kişiler</a>
            üzerinden ekleyin.
        </p>
    </x-form.group>
@endif

<x-form.group legend="İş bilgisi" icon="fa-solid fa-briefcase" description="Görev, maaş ve hesap">
    <x-form.select searchable hide-search name="occupation" label="Görev" :options="\App\Enums\EmployeeOccupation::options()" placeholder="Seçiniz" :autofocus="isset($employee)" />
    <x-form.input name="started_on" label="İşe başlama" type="date" />
    <x-form.input name="salary" label="Maaş" type="number" step="0.01" min="0" required />
    <x-form.input name="bank_account_no" label="Hesap no / IBAN" maxlength="100" />
    <x-form.checkbox name="active" label="Durum" text="Personel listelerinde görünsün" :checked="true" />
</x-form.group>
