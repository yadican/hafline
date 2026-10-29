@php($lastMovement = $instrument->movements->last())

<div class="panel-form-grid panel-form-grid-after">
    <x-form.group legend="Hareketler" icon="fa-solid fa-clock-rotate-left" description="Çekin / senedin geçtiği adımlar; yalnızca son adım geri alınabilir" class="is-wide">
        @if ($instrument->movements->isNotEmpty())
            <table class="panel-subtable">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>İşlem</th>
                        <th>Hesap / cari</th>
                        <th>Açıklama</th>
                        <th class="is-end"><span class="sr-only">İşlem</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($instrument->movements as $movement)
                        <tr>
                            <td>{{ $movement->occurred_on->format('d.m.Y') }}</td>
                            <td><i class="{{ $movement->type->icon() }} panel-muted-icon" aria-hidden="true"></i> {{ $movement->type->label() }}</td>
                            <td>{{ $movement->bank ? $movement->bank->type->label().' · '.$movement->bank->name : ($movement->party?->name ?? '—') }}</td>
                            <td>{{ $movement->description ?: '—' }}</td>
                            <td class="panel-actions-cell">
                                @if ($movement->is($lastMovement))
                                    @can('delete-financial-instrument')
                                        <form method="POST" action="{{ route('admin.financial-instruments.movements.undo', $instrument) }}" class="js-confirm" data-confirm-title="Son işlem geri alınsın mı?" data-confirm-text="&quot;{{ $movement->type->label() }}&quot; adımı silinir; cariye yazılan tutar ters kayıtla kapatılır, hesaba giren ya da çıkan para geri alınır." data-confirm-button="Geri al" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="panel-act panel-act-del" title="Geri al">
                                                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                                                <span class="sr-only">Geri al</span>
                                            </button>
                                        </form>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="panel-hint">Henüz işlem yok; {{ $instrument->direction === \App\Enums\InstrumentDirection::Received ? 'çek / senet portföyde bekliyor' : 'vadesinde ödenecek' }}.</p>
        @endif

        @if ($options['types'] === [])
            <p class="panel-hint">Bu kayıt kapandı; yeni işlem yapılamaz. Yanlışlık varsa son adımı geri alın.</p>
        @else
            @can('edit-financial-instrument')
                <form
                    method="POST"
                    action="{{ route('admin.financial-instruments.movements.store', $instrument) }}"
                    class="pt-3"
                    x-data="{ type: @js(old('type', $options['types'][0]->value)), needsAccount: @js($options['needs_account']), hints: @js(collect($options['types'])->mapWithKeys(fn ($type) => [$type->value => $type->hint()])) }"
                >
                    @csrf
                    <x-form.field label="İşlem" name="type">
                        <div class="panel-choices is-inline" role="radiogroup">
                            @foreach ($options['types'] as $type)
                                <label class="panel-choice">
                                    <input type="radio" name="type" value="{{ $type->value }}" x-model="type">
                                    <i class="{{ $type->icon() }} panel-muted-icon" aria-hidden="true"></i> {{ $type->action() }}
                                </label>
                            @endforeach
                        </div>
                    </x-form.field>
                    <p class="panel-hint" x-text="hints[type]"></p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-form.input name="occurred_on" label="Tarih" type="date" :value="today()" required />
                        <div x-show="needsAccount.includes(type)" x-cloak>
                            <x-form.select searchable name="bank_id" label="Hesap" placeholder="Hesap seçin" :options="$options['accounts']" :value="$options['deposit_bank']" />
                        </div>
                        <div x-show="type === 'endorse'" x-cloak>
                            <x-form.select searchable name="party_id" label="Verilen cari" placeholder="Cari seçin" :options="$options['parties']" />
                        </div>
                        <x-form.input name="description" label="Açıklama" maxlength="255" />
                    </div>
                    <div class="flex justify-end pt-3">
                        <x-ui.button type="submit" variant="create" icon="fa-solid fa-check">Kaydet</x-ui.button>
                    </div>
                </form>
            @endcan
        @endif
    </x-form.group>
</div>
