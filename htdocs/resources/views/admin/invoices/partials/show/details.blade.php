@php
    $status = $invoice->paymentStatus();
    $hasVat = (float) $invoice->vat_amount > 0;
    $grossProfit = $invoice->grossProfit();
    $costAmount = $invoice->costAmount();
@endphp
<div class="panel-form-grid is-detail">
    <x-form.group legend="Fatura" icon="fa-solid fa-file-invoice" description="Yön, numara, tarih ve vade; vade yalnız hatırlatır">
        <x-form.field label="Yön"><div>{{ $invoice->direction->label() }}</div></x-form.field>
        <x-form.field label="Fatura no"><div>{{ $invoice->invoice_no }}</div></x-form.field>
        <x-form.field label="Fatura tarihi"><div>{{ $invoice->issued_on->format('d.m.Y') }}</div></x-form.field>
        <x-form.field label="Vade"><div>{{ $invoice->due_on?->format('d.m.Y') ?? '—' }}</div></x-form.field>
        @if ($invoice->returnedInvoice)
            <x-form.field label="Bağlı alış">
                <div>
                    @can('view-invoice')
                        <a href="{{ route('admin.invoices.show', $invoice->returnedInvoice) }}">{{ $invoice->returnedInvoice->invoice_no }}</a>
                    @else
                        {{ $invoice->returnedInvoice->invoice_no }}
                    @endcan
                </div>
            </x-form.field>
        @endif
        <x-form.field label="Şantiye"><div>{{ $invoice->site?->name ?? '—' }}</div></x-form.field>
        <x-form.field label="Açıklama"><div>{{ $invoice->description ?: '—' }}</div></x-form.field>
    </x-form.group>

    <x-form.group legend="Cari" icon="fa-solid fa-scale-balanced" :description="$invoice->isDraft() ? 'Taslak cariye yazılmaz; Onayla ile yazılır' : 'Fatura kaydedildiği gün cariye yazılır'">
        @if ($invoice->vehicle)
            <x-form.field label="Plaka">
                <div>
                    @can('view-vehicle')
                        <a href="{{ route('admin.vehicles.show', $invoice->vehicle) }}" class="panel-plate-text">{{ $invoice->vehicle->plate }}</a>
                    @else
                        <span class="panel-plate-text">{{ $invoice->vehicle->plate }}</span>
                    @endcan
                </div>
            </x-form.field>
        @endif
        <x-form.field label="Cari">
            <div>
                @can('view-party')
                    <a href="{{ route('admin.parties.show', $invoice->party) }}">{{ $invoice->party?->name }}</a>
                @else
                    {{ $invoice->party?->name }}
                @endcan
            </div>
        </x-form.field>
        <x-form.field label="Cari satırı">
            @if ($ledgerEntry)
                <div>
                    {{ $ledgerEntry->occurred_on->format('d.m.Y') }} ·
                    {{ (float) $ledgerEntry->credit > 0 ? 'Alacak' : 'Borç' }}
                    <x-ui.money :value="(float) $ledgerEntry->credit > 0 ? $ledgerEntry->credit : $ledgerEntry->debit" />
                </div>
                @can('view-party')
                    <x-form.status>
                        <a href="{{ route('admin.parties.show', ['party' => $invoice->party, 'modul' => \App\Enums\Module::General->value]) }}#cari-hareketleri">Cari ekstresinde aç</a>
                    </x-form.status>
                @endcan
            @elseif ($invoice->isDraft())
                <div>Taslak — henüz yazılmadı</div>
            @else
                <div>—</div>
            @endif
        </x-form.field>
    </x-form.group>

    <x-form.group legend="Tutar ve ödeme" icon="fa-solid fa-coins" description="Ödenen ve kalan, faturaya bağlanan ödemelerden hesaplanır" class="is-wide">
        <div class="panel-detail-columns">
            @if ($hasVat)
                <x-form.field label="Matrah"><div><x-ui.money :value="$invoice->subtotal" /></div></x-form.field>
                <x-form.field label="KDV"><div><x-ui.money :value="$invoice->vat_amount" /></div></x-form.field>
            @endif
            <x-form.field label="Toplam"><div><x-ui.money :value="$invoice->total" /></div></x-form.field>
            @if ($costAmount !== null)
                <x-form.field label="Maliyet"><div><x-ui.money :value="$costAmount" /></div></x-form.field>
                <x-form.field label="Brüt kâr"><div><x-ui.money :value="$grossProfit" /></div></x-form.field>
            @endif
            <x-form.field label="Ödenen"><div><x-ui.money :value="$invoice->paidAmount()" /></div></x-form.field>
            <x-form.field label="Kalan"><div><x-ui.money :value="$invoice->remainingAmount()" /></div></x-form.field>
            <x-form.field label="Durum">
                <div>
                    @if ($invoice->isDraft())
                        <x-ui.badge tone="pasif" icon="fa-solid fa-file">Taslak</x-ui.badge>
                    @else
                        <x-ui.badge :tone="$status->tone()">{{ $status->label($invoice->direction) }}</x-ui.badge>
                        @if ($invoice->isOverdue())
                            <x-ui.badge tone="gider" icon="fa-solid fa-clock">Vadesi geçti</x-ui.badge>
                        @endif
                    @endif
                </div>
            </x-form.field>
        </div>
    </x-form.group>

    <x-form.group legend="Kayıt" icon="fa-solid fa-clock-rotate-left" description="Faturayı giren kullanıcı ve kayıt zamanı" class="is-wide">
        <div class="panel-detail-columns">
            <x-form.field label="Giren"><div>{{ $invoice->creator?->name ?: '—' }}</div></x-form.field>
            <x-form.field label="Kayıt zamanı"><div>{{ $invoice->created_at?->format('d.m.Y H:i') ?: '—' }}</div></x-form.field>
        </div>
    </x-form.group>
</div>
