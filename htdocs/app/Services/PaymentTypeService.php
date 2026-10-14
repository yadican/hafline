<?php

namespace App\Services;

use App\Models\PaymentType;
use App\Repositories\PaymentTypeRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentTypeService extends Service
{
    public function __construct(private PaymentTypeRepository $paymentTypes) {}

    /**
     * @param  array{q?: string, active?: string, kind?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->paymentTypes->search($filters, $sort, $perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PaymentType
    {
        /** @var PaymentType */
        return $this->transaction(fn () => $this->paymentTypes->create($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(PaymentType $paymentType, array $attributes): PaymentType
    {
        /** @var PaymentType */
        return $this->transaction(fn () => $this->paymentTypes->update($paymentType, $attributes));
    }

    public function delete(PaymentType $paymentType): void
    {
        $this->transaction(fn () => $this->paymentTypes->delete($paymentType));
    }
}
