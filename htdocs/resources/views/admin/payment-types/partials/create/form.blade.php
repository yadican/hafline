<form method="POST" action="{{ route('admin.payment-types.store') }}">
    @csrf
    <x-form.grid>
        <x-form.group legend="Ödeme tipi" icon="fa-solid fa-money-check-dollar" description="Ad, tür ve kullanım durumu">
            <x-form.input name="name" label="Ad" maxlength="50" required autofocus />
            <x-form.radios name="kind" label="Tür" :options="\App\Enums\PaymentKind::options()" />
            <x-form.checkbox name="active" label="Durum" text="Seçim listelerinde gösterilsin" checked />
        </x-form.group>

        <x-form.actions label="Kaydet" :cancel="route('admin.payment-types.index')" />
    </x-form.grid>
</form>
