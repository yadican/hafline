<form method="POST" action="{{ route('admin.cash-categories.update', $cashCategory) }}">
    @csrf
    @method('PUT')
    <x-form.grid :model="$cashCategory">
        <x-form.group legend="Kasa kategorisi" icon="fa-solid fa-tags" description="Kategori adı, yönü ve kullanım durumu">
            <x-form.input name="name" label="Ad" maxlength="100" required autofocus />
            <x-form.select searchable name="parent_id" label="Grup" placeholder="Grup yok (kendisi grup olabilir)" :options="$groups" />
            <x-form.radios name="direction" label="Yön" :options="\App\Enums\CashDirection::options()" />
            <p class="panel-hint">Gider kategorileri ödeme/çıkış, gelir kategorileri tahsilat/giriş kayıtlarında listelenir.</p>
            <x-form.checkbox name="active" label="Durum" text="Seçim listelerinde gösterilsin" />
        </x-form.group>

        <x-form.actions label="Güncelle" :cancel="route('admin.cash-categories.index')" />
    </x-form.grid>
</form>
