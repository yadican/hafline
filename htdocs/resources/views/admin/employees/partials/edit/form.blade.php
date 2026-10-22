<form method="POST" action="{{ route('admin.employees.update', $employee) }}">
    @csrf
    @method('PUT')
    <x-form.grid :model="$values">
        <x-form.group legend="Kişi" icon="fa-solid fa-user" description="Ad ve iletişim bilgisi cari karttan yönetilir">
            <p class="panel-hint">
                <strong>{{ $employee->name }}</strong>
                —
                <a href="{{ route('admin.parties.edit', $employee->party) }}" class="underline">Cari kartı düzenle</a>
            </p>
        </x-form.group>

        @include('admin.employees.partials.form.fields')

        <x-form.group legend="İş dönemi" icon="fa-solid fa-calendar-days" description="Çıkış veya yeniden giriş kaydı">
            @if ($values['has_open_period'] ?? false)
                <x-form.input name="exit_on" label="İşten çıkış" type="date" />
                <p class="panel-hint">Açık dönem: {{ $values['open_period_started_on'] ? \Illuminate\Support\Carbon::parse($values['open_period_started_on'])->format('d.m.Y') : '—' }}</p>
            @else
                <x-form.input name="rehire_on" label="Yeniden işe giriş" type="date" />
                <p class="panel-hint">Açık iş dönemi yok. Yeniden giriş tarihi ile yeni dönem açılır.</p>
            @endif
        </x-form.group>

        <x-form.actions label="Güncelle" :cancel="route('admin.employees.index')" />
    </x-form.grid>
</form>
