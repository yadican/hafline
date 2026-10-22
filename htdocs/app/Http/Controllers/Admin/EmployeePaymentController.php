<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\EmployeePaymentRequest;
use App\Http\Requests\Admin\EmployeePaymentSearchRequest;
use App\Models\EmployeePayment;
use App\Services\EmployeePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmployeePaymentController extends AdminController
{
    protected ?string $permissionResource = 'employee-payment';

    public function __construct(private EmployeePaymentService $payments) {}

    public function index(EmployeePaymentSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.employee-payments.index', [
            'title' => 'Personel ödeme girişi',
            'payments' => $this->payments->paginate($filters, $request->sort()),
            'filters' => $filters,
            'options' => $this->payments->formOptions($filters['employee_id'] ?? null),
        ]);
    }

    public function create(): View
    {
        return view('admin.employee-payments.create', [
            'title' => 'Yeni personel ödemesi',
            'options' => $this->payments->formOptions(),
        ]);
    }

    public function store(EmployeePaymentRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->payments->create($request->validated()),
            'admin.employee-payments.index',
            'Ödeme kaydedildi.',
        );
    }

    public function edit(EmployeePayment $employeePayment): View
    {
        return view('admin.employee-payments.edit', [
            'title' => 'Personel ödemesini düzenle',
            'payment' => $employeePayment->load('employee.party'),
            'options' => $this->payments->formOptions($employeePayment->employee_id),
        ]);
    }

    public function update(EmployeePaymentRequest $request, EmployeePayment $employeePayment): RedirectResponse
    {
        return $this->complete(
            fn () => $this->payments->update($employeePayment, $request->validated()),
            'admin.employee-payments.index',
            'Ödeme güncellendi.',
        );
    }

    public function destroy(EmployeePayment $employeePayment): RedirectResponse
    {
        return $this->complete(
            fn () => $this->payments->delete($employeePayment),
            'admin.employee-payments.index',
            'Ödeme silindi.',
        );
    }
}
