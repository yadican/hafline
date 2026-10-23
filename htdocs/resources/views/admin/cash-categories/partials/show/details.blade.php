<div class="panel-form-grid">
    <x-form.group legend="Kasa kategorisi" icon="fa-solid fa-tags" description="Kategori adı, yönü ve kullanım durumu">
        <x-form.field label="Ad">
            <div>{{ $cashCategory->name }}</div>
        </x-form.field>
        <x-form.field label="Yön">
            <div><x-ui.enum-badge :value="$cashCategory->direction" text /></div>
        </x-form.field>
        <x-form.field label="Durum">
            <div><x-ui.active-badge :active="$cashCategory->active" /></div>
        </x-form.field>
    </x-form.group>
</div>
