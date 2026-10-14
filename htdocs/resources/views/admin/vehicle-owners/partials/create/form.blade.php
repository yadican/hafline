<form method="POST" action="{{ route('admin.vehicle-owners.store') }}">
    @csrf
    <x-form.grid>
        <x-form.group legend="Araç sahibi" icon="fa-solid fa-id-card" description="Ad ve soyad">
            <x-form.input name="first_name" label="Ad" maxlength="80" required autofocus />
            <x-form.input name="last_name" label="Soyad" maxlength="80" required />
        </x-form.group>

        <x-form.group legend="İletişim" icon="fa-solid fa-address-book" description="Telefon ve adres">
            <x-form.input name="phone" label="Telefon" type="tel" maxlength="20" />
            <x-form.input name="mobile" label="Cep telefonu" type="tel" maxlength="20" />
            <x-form.textarea name="address" label="Adres" rows="2" maxlength="255" />
        </x-form.group>

        <x-form.actions label="Kaydet" :cancel="route('admin.vehicle-owners.index')" />
    </x-form.grid>
</form>
