<form method="POST" action="{{ route('admin.employees.update', $employee) }}">
    @csrf
    @method('PUT')
    <x-form.grid :model="$values">
        @include('admin.employees.partials.form.fields')

        <x-form.group legend="İş dönemi" icon="fa-solid fa-calendar-days" description="Çıkış, yeniden giriş ve dönem geçmişi" class="is-wide">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @if ($values['has_open_period'] ?? false)
                    <x-form.input name="exit_on" label="İşten çıkış" type="date">
                        <p class="panel-hint">Açık dönem: {{ $values['open_period_started_on'] ? \Illuminate\Support\Carbon::parse($values['open_period_started_on'])->format('d.m.Y') : '—' }}</p>
                    </x-form.input>
                @else
                    <x-form.input name="rehire_on" label="Yeniden işe giriş" type="date">
                        <p class="panel-hint">Açık iş dönemi yok. Yeniden giriş tarihi ile yeni dönem açılır.</p>
                    </x-form.input>
                @endif
                <x-form.field label="Dönem geçmişi" class="lg:col-span-2">
                    @include('admin.employees.partials.edit.periods')
                </x-form.field>
            </div>
        </x-form.group>

        <x-form.actions label="Güncelle" :cancel="route('admin.employees.index')" />
    </x-form.grid>
</form>
