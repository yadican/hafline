<?php

namespace App\Http\Requests\Admin;

use App\Models\DumpSite;
use App\Models\Site;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HaulRouteRequest extends FormRequest
{
    public const PRICE_RULES = ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'price' => $this->input('price') ?? 0,
            'bonus' => $this->input('bonus') ?? 0,
            'active' => $this->boolean('active'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'site_id' => ['required', 'uuid', Rule::exists(Site::class, 'id')->withoutTrashed()],
            'dump_site_id' => [
                'required',
                'uuid',
                Rule::exists(DumpSite::class, 'id')->withoutTrashed(),
                Rule::unique('haul_routes', 'dump_site_id')
                    ->where('site_id', $this->input('site_id'))
                    ->ignore($this->route('haul_route'))
                    ->withoutTrashed(),
            ],
            'price' => self::PRICE_RULES,
            'bonus' => self::PRICE_RULES,
            'class_prices' => ['array'],
            'class_prices.*' => self::PRICE_RULES,
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dump_site_id.unique' => 'Bu şantiye ile döküm yeri arasında zaten bir güzergah var.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'site_id' => 'şantiye',
            'dump_site_id' => 'döküm yeri',
            'price' => 'temel fiyat',
            'bonus' => 'prim',
            'class_prices.*' => 'sınıf fiyatı',
            'active' => 'aktif',
        ];
    }
}
