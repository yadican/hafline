<x-form.group legend="Malzeme" icon="fa-solid fa-cubes-stacked" description="Ad, birim ve kullanım durumu">
    <x-form.input name="name" label="Ad" maxlength="80" required autofocus />
    <x-form.radios name="unit" label="Birim" :options="\App\Enums\MaterialUnit::options()" />
    <x-form.checkbox name="active" label="Durum" text="Seferde seçilebilsin" checked />
</x-form.group>
