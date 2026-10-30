<x-form.field :label="$label" for="invoice-attachments" name="attachments">
    <input id="invoice-attachments" name="attachments[]" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" multiple class="panel-file">
    <p class="panel-hint">Resim (JPEG, PNG, WebP) ya da PDF; en fazla {{ $room }} dosya, PDF en fazla {{ \App\Services\Api\SecureImageIngest::MAX_PDF_BYTES / 1024 / 1024 }} MB.</p>
</x-form.field>
