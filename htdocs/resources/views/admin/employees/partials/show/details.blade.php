<div class="panel-form-grid is-detail">
    <x-form.group legend="Kişi" icon="fa-solid fa-user" description="Ad ve iletişim bilgisi cari karttan yönetilir">
        <x-form.field label="Ad soyad">
            <div>{{ $employee->name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Telefon">
            <div>{{ $employee->party?->mobile ?: ($employee->party?->phone ?: '—') }}</div>
        </x-form.field>
        <x-form.field label="E-posta">
            <div>{{ $employee->party?->email ?: '—' }}</div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="İş bilgisi" icon="fa-solid fa-briefcase" description="Görev, maaş ve hesap">
        <x-form.field label="Görev">
            <div>{{ $employee->occupation?->label() ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="İşe başlama">
            <div>{{ $employee->started_on?->format('d.m.Y') ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Maaş">
            <div><x-ui.money :value="$employee->salary" /></div>
        </x-form.field>
        <x-form.field label="Hesap no / IBAN">
            <div>{{ $employee->bank_account_no ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Durum">
            <div><x-ui.active-badge :active="$employee->active" /></div>
        </x-form.field>
    </x-form.group>
</div>
