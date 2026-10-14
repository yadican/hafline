<?php

namespace App\Http\Requests\Admin;

use App\Enums\HaulShift;
use App\Models\DumpSite;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\Site;
use App\Models\Vehicle;
use App\Services\HaulService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class HaulRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'plate' => mb_strtoupper((string) $this->input('plate'), 'UTF-8'),
            'tonnage' => $this->input('tonnage') ?: 0,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ticket_no' => ['required', 'string', 'max:20', $this->uniqueTicket()],
            'plate' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (! Vehicle::findByPlate((string) $value)) {
                    $fail('Kayıtlı bir plaka girin.');
                }
            }],
            'trips' => [Rule::excludeIf($this->haul() !== null), 'required', 'integer', 'between:1,50'],
            'hauled_at' => ['required', 'date'],
            'site_id' => ['required', 'integer', Rule::exists(Site::class, 'id')],
            'dump_site_id' => ['required', 'integer', Rule::exists(DumpSite::class, 'id')],
            'tonnage' => ['required', 'integer', 'between:0,1000'],
            'shift' => ['required', Rule::enum(HaulShift::class)],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['site_id', 'dump_site_id'])) {
                    return;
                }

                if (! HaulRoute::between($this->integer('site_id'), $this->integer('dump_site_id'))) {
                    $validator->errors()->add('dump_site_id', 'Bu şantiye ile döküm yeri arasında tanımlı güzergah yok.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ticket_no' => 'fiş no',
            'plate' => 'plaka',
            'trips' => 'sefer sayısı',
            'hauled_at' => 'tarih / saat',
            'site_id' => 'şantiye',
            'dump_site_id' => 'döküm yeri',
            'tonnage' => 'tonaj',
            'shift' => 'vardiya',
        ];
    }

    private function haul(): ?Haul
    {
        $haul = $this->route('haul');

        return $haul instanceof Haul ? $haul : null;
    }

    private function uniqueTicket(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->haul()?->ticket_no === $value) {
                return;
            }

            if (app(HaulService::class)->ticketTaken((string) $value)) {
                $fail('Bu fiş numarası daha önce kullanılmış.');
            }
        };
    }
}
