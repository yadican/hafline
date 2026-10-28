<x-form.group legend="Hesap" icon="fa-solid fa-wallet" description="Hesabın türü, adı ve kullanım durumu">
    <x-form.radios name="type" label="Tür" :options="\App\Enums\AccountType::options()" />
    <x-form.input name="name" label="Ad" maxlength="100" required autofocus placeholder="Merkez Kasa, Ziraat Bankası Ticari, Garanti POS..." />
    <x-form.checkbox name="active" label="Durum" text="Seçim listelerinde gösterilsin" :checked="true" />
</x-form.group>

<x-form.group legend="Açılış" icon="fa-solid fa-flag-checkered" description="Hesap sisteme girildiği günkü bakiye">
    <x-form.money name="opening_balance" label="Açılış bakiyesi" />
    <x-form.input name="opened_on" label="Açılış tarihi" type="date" />
    <p class="panel-hint">Kredi kartında borç bakiyesini eksi olarak yazın.</p>
</x-form.group>

<x-form.group legend="Banka bilgileri" icon="fa-solid fa-building-columns" description="Kasa hesaplarında boş bırakılır">
    <x-form.input name="branch" label="Şube" maxlength="100" />
    <x-form.input name="iban" label="IBAN" maxlength="34" placeholder="TR00 0000 0000 0000 0000 0000 00" />
    <x-form.input name="account_no" label="Hesap / terminal no" maxlength="50" />
    <x-form.input name="representative" label="Temsilci" maxlength="100" />
    <x-form.input name="phone" label="Telefon" type="tel" maxlength="20" />
</x-form.group>
