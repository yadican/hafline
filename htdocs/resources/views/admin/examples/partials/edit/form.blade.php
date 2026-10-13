<form method="POST" action="{{ route('admin.examples.update', $example) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <x-form.hidden name="source" value="panel" :model="$example" />
    <x-form.grid :model="$example">
        <x-form.input name="title" label="Metin" required />
        <x-form.input name="secret" label="Şifre" type="password" autocomplete="new-password" />
        <x-form.input name="email" label="E-posta" type="email" />
        <x-form.input name="phone" label="Telefon" type="tel" />
        <x-form.input name="website" label="Web adresi" type="url" />
        <x-form.input name="reference" label="Arama" type="search" />
        <x-form.textarea name="note" label="Uzun metin" />
        <x-form.input name="quantity" label="Sayı" type="number" min="0" />
        <x-form.input name="amount" label="Tutar" type="number" min="0" step="0.01" />
        <x-form.input name="color" label="Renk" type="color" />
        <x-form.input name="level" label="Aralık" type="range" min="0" max="100" />
        <x-form.input name="event_date" label="Tarih" type="date" />
        <x-form.input name="event_time" label="Saat" type="time" />
        <x-form.input name="starts_at" label="Tarih ve saat" type="datetime-local" />
        <x-form.input name="event_month" label="Ay" type="month" />
        <x-form.input name="event_week" label="Hafta" type="week" />
        <x-form.select
            name="status"
            label="Seçim listesi"
            placeholder="Seçin"
            :options="\App\Enums\ExampleStatus::options()"
        />
        <x-form.select
            name="channels"
            label="Çoklu seçim"
            multiple
            size="3"
            :options="\App\Enums\ExampleChannel::options()"
        />
        <x-form.checkboxes
            name="tags"
            label="Onay kutuları"
            :options="\App\Enums\ExampleTag::options()"
        />
        <x-form.radios
            name="shift"
            label="Tek seçim"
            :options="\App\Enums\ExampleShift::options()"
        />
        <x-form.checkbox name="active" label="Aktif" text="Kayıt aktif" />
        <x-form.file name="attachment" label="Dosya" />
        <x-form.actions label="Güncelle" :cancel="route('admin.examples.index')" />
    </x-form.grid>
</form>
