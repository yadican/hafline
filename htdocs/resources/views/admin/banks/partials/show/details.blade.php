<div class="panel-form-grid">
    <x-form.group legend="Banka" icon="fa-solid fa-building-columns" description="Banka adı ve kullanım durumu">
        <x-form.field label="Ad">
            <div>{{ $bank->name }}</div>
        </x-form.field>
        <x-form.field label="Durum">
            <div><x-ui.active-badge :active="$bank->active" /></div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="İletişim ve hesap" icon="fa-solid fa-address-book" description="Temsilci, telefon ve hesap numarası">
        <x-form.field label="Temsilci">
            <div>{{ $bank->representative ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Telefon">
            <div>{{ $bank->phone ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Hesap no / IBAN">
            <div>{{ $bank->account_no ?: '—' }}</div>
        </x-form.field>
    </x-form.group>
</div>
