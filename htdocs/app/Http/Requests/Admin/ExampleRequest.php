<?php

namespace App\Http\Requests\Admin;

use App\Enums\ExampleChannel;
use App\Enums\ExampleShift;
use App\Enums\ExampleStatus;
use App\Enums\ExampleTag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExampleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->boolean('active'),
            'status' => $this->input('status') ?: null,
            'shift' => $this->input('shift') ?: null,
            'tags' => $this->input('tags', []),
            'channels' => $this->input('channels', []),
            'source' => $this->input('source', 'panel'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
            'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'website' => ['nullable', 'url', 'max:200'],
            'secret' => ['nullable', 'string', 'max:80'],
            'reference' => ['nullable', 'string', 'max:80'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'event_date' => ['nullable', 'date'],
            'event_time' => ['nullable', 'date_format:H:i'],
            'starts_at' => ['nullable', 'date'],
            'event_month' => ['nullable', 'date_format:Y-m'],
            'event_week' => ['nullable', 'string', 'max:8'],
            'color' => ['nullable', 'string', 'max:7'],
            'level' => ['nullable', 'integer', 'between:0,100'],
            'status' => ['nullable', Rule::enum(ExampleStatus::class)],
            'shift' => ['nullable', Rule::enum(ExampleShift::class)],
            'tags' => ['array'],
            'tags.*' => [Rule::enum(ExampleTag::class)],
            'channels' => ['array'],
            'channels.*' => [Rule::enum(ExampleChannel::class)],
            'active' => ['boolean'],
            'attachment' => ['nullable', 'file', 'max:4096'],
            'source' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'başlık',
            'note' => 'not',
            'email' => 'e-posta',
            'phone' => 'telefon',
            'website' => 'web adresi',
            'secret' => 'şifre',
            'reference' => 'arama',
            'quantity' => 'adet',
            'amount' => 'tutar',
            'event_date' => 'tarih',
            'event_time' => 'saat',
            'starts_at' => 'başlangıç',
            'event_month' => 'ay',
            'event_week' => 'hafta',
            'color' => 'renk',
            'level' => 'seviye',
            'status' => 'durum',
            'shift' => 'vardiya',
            'tags' => 'etiketler',
            'channels' => 'kanallar',
            'active' => 'aktif',
            'attachment' => 'dosya',
        ];
    }
}
