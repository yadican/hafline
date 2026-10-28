<x-form.group legend="Etiket" icon="fa-solid fa-tag" description="Etiket adı, rengi ve kullanım durumu">
    <x-form.input name="name" label="Ad" maxlength="60" required autofocus />
    <x-form.radios name="tone" label="Renk" :options="\App\Enums\TagTone::options()" />
    <x-form.checkbox name="active" label="Durum" text="Cari kartlarda seçilebilsin" :checked="true" />
</x-form.group>
