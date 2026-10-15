<form method="POST" action="{{ route('admin.cash-categories.store') }}">
    @csrf
    <x-form.grid :model="['direction' => \App\Enums\CashDirection::Expense->value]">
        <x-form.group legend="Kasa kategorisi" icon="fa-solid fa-tags" description="Kategori adı, yönü ve kullanım durumu">
            <x-form.input name="name" label="Ad" maxlength="100" required autofocus />
            <x-form.radios name="direction" label="Yön" :options="\App\Enums\CashDirection::options()" />
            <p class="panel-hint">Gider kategorileri ödeme/çıkış, gelir kategorileri tahsilat/giriş kayıtlarında listelenir.</p>
            <x-form.checkbox name="active" label="Durum" text="Seçim listelerinde gösterilsin" checked />
        </x-form.group>

        <x-form.actions label="Kaydet" :cancel="route('admin.cash-categories.index')" />
    </x-form.grid>
</form>
