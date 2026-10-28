<x-form.group legend="Ocak / yükleme yeri" icon="fa-solid fa-mountain" description="Malzeme satışlı seferde ocağın carisine alış bedeli alacak yazılır; carisi olmayan yer sadece nakliyede kullanılır">
    <x-form.input name="name" label="Ad" maxlength="255" required autofocus />
    <x-form.select name="party_id" label="Ocak carisi" :options="$parties" placeholder="Cari yok (sadece nakliye)" searchable />
    <x-form.checkbox name="active" label="Durum" text="Seferde seçilebilsin" checked />
</x-form.group>
