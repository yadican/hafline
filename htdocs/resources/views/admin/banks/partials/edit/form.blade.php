<form method="POST" action="{{ route('admin.banks.update', $bank) }}">
    @csrf
    @method('PUT')
    <x-form.grid :model="$bank">
        <x-form.group legend="Banka" icon="fa-solid fa-building-columns" description="Banka adı ve kullanım durumu">
            <x-form.input name="name" label="Ad" maxlength="100" required autofocus />
            <x-form.checkbox name="active" label="Durum" text="Seçim listelerinde gösterilsin" />
        </x-form.group>

        <x-form.group legend="İletişim ve hesap" icon="fa-solid fa-address-book" description="Temsilci, telefon ve hesap numarası">
            <x-form.input name="representative" label="Temsilci" maxlength="100" />
            <x-form.input name="phone" label="Telefon" type="tel" maxlength="20" />
            <x-form.input name="account_no" label="Hesap no / IBAN" maxlength="50" />
        </x-form.group>

        <x-form.actions label="Güncelle" :cancel="route('admin.banks.index')" />
    </x-form.grid>
</form>
