<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesInvoiceAttachments;
use App\Models\Invoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class InvoiceAttachmentRequest extends FormRequest
{
    use ValidatesInvoiceAttachments;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->attachmentRules(required: true);
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $invoice = $this->route('invoice');

                if ($invoice instanceof Invoice) {
                    $this->checkAttachmentRoom($validator, $invoice);
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->attachmentAttributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->attachmentMessages();
    }
}
