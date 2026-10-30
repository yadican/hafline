@php($maxAttachments = \App\Services\InvoiceService::MAX_ATTACHMENTS)
<div class="panel-form-grid panel-form-grid-after" id="dosyalar">
    <x-form.group legend="Dosyalar" icon="fa-solid fa-paperclip" :description="'Faturanın resmi ya da PDF\'i; en fazla '.$maxAttachments.' dosya'" class="is-wide">
        @if ($invoice->attachments->isNotEmpty())
            <div class="flex flex-wrap gap-3">
                @foreach ($invoice->attachments as $attachment)
                    @php($attachmentUrl = route('admin.invoices.attachments.show', [$invoice, $attachment]))
                    <div class="flex flex-col items-start gap-1">
                        @if ($attachment->isPdf())
                            <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener" class="flex h-48 w-36 flex-col items-center justify-center gap-2 rounded border border-[var(--ui-border-soft)] text-center">
                                <i class="fa-solid fa-file-pdf text-4xl text-red-600" aria-hidden="true"></i>
                                <span>PDF'i aç</span>
                            </a>
                        @else
                            <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener" class="inline-block">
                                <img
                                    src="{{ $attachmentUrl }}"
                                    alt="Fatura {{ $invoice->invoice_no }} resmi {{ $loop->iteration }}"
                                    class="max-h-48 max-w-full rounded border border-[var(--ui-border-soft)] object-contain"
                                >
                            </a>
                        @endif
                        @can('edit-invoice')
                            <form method="POST" action="{{ route('admin.invoices.attachments.destroy', [$invoice, $attachment]) }}" class="js-confirm" data-confirm-title="Dosya silinsin mi?" data-confirm-text="Dosya bu faturadan kaldırılır." data-confirm-button="Sil">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="panel-act panel-act-del" title="Dosyayı sil">
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                    <span class="sr-only">Dosyayı sil</span>
                                </button>
                            </form>
                        @endcan
                    </div>
                @endforeach
            </div>
        @else
            <p class="panel-hint">Henüz dosya yok.</p>
        @endif

        @can('edit-invoice')
            @if ($invoice->attachments->count() < $maxAttachments)
                <form method="POST" action="{{ route('admin.invoices.attachments.store', $invoice) }}" enctype="multipart/form-data" class="pt-3">
                    @csrf
                    @include('admin.invoices.partials.attachment-input', ['label' => 'Dosya ekle', 'room' => $maxAttachments - $invoice->attachments->count()])
                    <div class="flex justify-end pt-3">
                        <x-ui.button type="submit" variant="create" icon="fa-solid fa-upload">Yükle</x-ui.button>
                    </div>
                </form>
            @endif
        @endcan
    </x-form.group>
</div>
