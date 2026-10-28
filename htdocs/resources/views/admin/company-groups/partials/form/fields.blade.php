<x-form.group legend="Şirket grubu" icon="fa-solid fa-sitemap" description="Grup adı ve kullanım durumu">
    <x-form.input name="name" label="Ad" maxlength="100" required autofocus />
    <x-form.checkbox name="active" label="Durum" text="Seçim listelerinde gösterilsin" :checked="true" />
    <p class="panel-hint">Şirketler gruba kendi cari kartlarından (Şirketler → Düzenle) bağlanır.</p>
</x-form.group>
