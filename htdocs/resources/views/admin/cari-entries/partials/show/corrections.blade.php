<div class="panel-form-grid panel-form-grid-after" id="duzeltme-gecmisi">
    <x-form.group legend="Düzeltme geçmişi" icon="fa-solid fa-clock-rotate-left" description="Bu hareketin cari, tutar veya yönünü değiştiren düzenlemeler: önce ve sonra yazılan satırlar" class="is-wide">
        @if ($corrections->isNotEmpty())
            <table class="panel-subtable">
                <thead>
                    <tr>
                        <th>Değişiklik zamanı</th>
                        <th>Kullanıcı</th>
                        <th>Önce</th>
                        <th>Sonra</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($corrections as $correction)
                        <tr>
                            <td>{{ $correction['changed_at']->format('d.m.Y H:i') }}</td>
                            <td>{{ $correction['user']?->name ?? 'Sistem' }}</td>
                            <td>
                                @foreach ($correction['before'] as $line)
                                    @include('admin.cari-entries.partials.show.correction-line', ['line' => $line])
                                @endforeach
                            </td>
                            <td>
                                @forelse ($correction['after'] as $line)
                                    @include('admin.cari-entries.partials.show.correction-line', ['line' => $line])
                                @empty
                                    Cariye yazılan satır kaldırıldı
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="panel-hint">Bu hareket kaydedildiğinden beri cari, tutar veya yönü değiştirilmedi.</p>
        @endif
    </x-form.group>
</div>
