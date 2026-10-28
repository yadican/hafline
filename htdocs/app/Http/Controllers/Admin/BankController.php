<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\BankRequest;
use App\Http\Requests\Admin\BankSearchRequest;
use App\Models\Bank;
use App\Services\BankService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BankController extends AdminController
{
    protected ?string $permissionResource = 'bank';

    public function __construct(private BankService $banks) {}

    public function index(BankSearchRequest $request): View
    {
        $filters = $request->filters();
        $banks = $this->banks->paginate($filters, $request->sort());

        return view('admin.banks.index', [
            'title' => 'Kasa / Banka / POS',
            'banks' => $banks,
            'balances' => $this->banks->balances($banks->pluck('id')->all()),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.banks.create', [
            'title' => 'Yeni hesap',
        ]);
    }

    public function store(BankRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->banks->create($request->validated()),
            'admin.banks.index',
            'Hesap oluşturuldu.',
        );
    }

    public function show(Bank $bank): View
    {
        return view('admin.banks.show', [
            'title' => 'Hesap detayı',
            'bank' => $bank,
            'balance' => $this->banks->balances([$bank->id])[$bank->id],
        ]);
    }

    public function edit(Bank $bank): View
    {
        return view('admin.banks.edit', [
            'title' => 'Hesabı düzenle',
            'bank' => $bank,
        ]);
    }

    public function update(BankRequest $request, Bank $bank): RedirectResponse
    {
        return $this->complete(
            fn () => $this->banks->update($bank, $request->validated()),
            'admin.banks.index',
            'Hesap güncellendi.',
        );
    }

    public function destroy(Bank $bank): RedirectResponse
    {
        return $this->complete(
            fn () => $this->banks->delete($bank),
            'admin.banks.index',
            'Hesap silindi.',
        );
    }
}
