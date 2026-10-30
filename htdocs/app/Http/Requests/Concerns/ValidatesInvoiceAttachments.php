<?php

namespace App\Http\Requests\Concerns;

use App\Models\Invoice;
use App\Services\Api\SecureImageIngest;
use App\Services\InvoiceService;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Invoice files: JPEG, PNG or WebP pictures and PDFs, at most four per invoice counting the
 * ones it already has. A PDF is stored as uploaded, so its size and content are checked here.
 */
trait ValidatesInvoiceAttachments
{
    /**
     * @return array<string, array<mixed>>
     */
    protected function attachmentRules(bool $required): array
    {
        return [
            'attachments' => [$required ? 'required' : 'nullable', 'array', 'max:'.InvoiceService::MAX_ATTACHMENTS],
            'attachments.*' => [
                'file',
                'mimes:jpeg,png,webp,pdf',
                'max:15360',
                function (string $attribute, mixed $file, Closure $fail): void {
                    $files = app(SecureImageIngest::class);
                    $problem = $file instanceof UploadedFile && $files->isPdf($file) ? $files->pdfProblem($file) : null;

                    if ($problem !== null) {
                        $fail($problem);
                    }
                },
            ],
        ];
    }

    protected function checkAttachmentRoom(Validator $validator, ?Invoice $invoice): void
    {
        if ($validator->errors()->has('attachments')) {
            return;
        }

        $free = InvoiceService::MAX_ATTACHMENTS - ($invoice?->attachments()->count() ?? 0);

        if (count((array) $this->file('attachments', [])) > $free) {
            $validator->errors()->add('attachments', $free > 0
                ? sprintf('Bu faturaya en fazla %d dosya daha eklenebilir.', $free)
                : sprintf('Bir faturada en fazla %d dosya olabilir; önce birini silin.', InvoiceService::MAX_ATTACHMENTS));
        }
    }

    /**
     * @return array<string, string>
     */
    protected function attachmentAttributes(): array
    {
        return [
            'attachments' => 'fatura dosyası',
            'attachments.*' => 'fatura dosyası',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function attachmentMessages(): array
    {
        return [
            'attachments.*.mimes' => 'Fatura dosyası JPEG, PNG, WebP ya da PDF olmalı.',
        ];
    }
}
