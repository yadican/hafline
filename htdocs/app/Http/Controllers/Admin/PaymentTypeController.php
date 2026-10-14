<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\PaymentTypeRequest;
use App\Http\Requests\Admin\PaymentTypeSearchRequest;
use App\Models\PaymentType;
use App\Services\PaymentTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentTypeController extends AdminController
{
    public function __construct(private PaymentTypeService $paymentTypes) {}

    public function index(PaymentTypeSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.payment-types.index', [
            'title' => 'Ödeme tipleri',
            'paymentTypes' => $this->paymentTypes->paginate($filters, $request->sort()),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.payment-types.create', [
            'title' => 'Yeni ödeme tipi',
        ]);
    }

    public function store(PaymentTypeRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->paymentTypes->create($request->validated()),
            'admin.payment-types.index',
            'Ödeme tipi oluşturuldu.',
        );
    }

    public function edit(PaymentType $paymentType): View
    {
        return view('admin.payment-types.edit', [
            'title' => 'Ödeme tipini düzenle',
            'paymentType' => $paymentType,
        ]);
    }

    public function update(PaymentTypeRequest $request, PaymentType $paymentType): RedirectResponse
    {
        return $this->complete(
            fn () => $this->paymentTypes->update($paymentType, $request->validated()),
            'admin.payment-types.index',
            'Ödeme tipi güncellendi.',
        );
    }

    public function destroy(PaymentType $paymentType): RedirectResponse
    {
        return $this->complete(
            fn () => $this->paymentTypes->delete($paymentType),
            'admin.payment-types.index',
            'Ödeme tipi silindi.',
        );
    }
}
