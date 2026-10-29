<div class="panel-form-grid panel-form-grid-after">
    <x-form.group legend="İş dönemleri" icon="fa-solid fa-calendar-days" description="İşe giriş ve çıkış geçmişi" class="is-wide">
        @if ($employee->periods->isNotEmpty())
            <table class="panel-subtable">
                <thead>
                    <tr>
                        <th>Giriş</th>
                        <th>Çıkış</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($employee->periods as $period)
                        <tr>
                            <td>{{ $period->started_on?->format('d.m.Y') ?: '—' }}</td>
                            <td>{{ $period->ended_on?->format('d.m.Y') ?: 'Devam ediyor' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="panel-hint">Dönem kaydı yok.</p>
        @endif
    </x-form.group>
</div>
