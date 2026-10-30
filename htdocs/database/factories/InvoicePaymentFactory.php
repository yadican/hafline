<?php

namespace Database\Factories;

use App\Enums\InvoiceDirection;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\CompanyTransaction;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Writes the match row and a bare cash payment on the invoice's party, without posting it to the
 * cari; real matches go through InvoicePaymentService. By default the payment settles the whole invoice.
 *
 * @extends Factory<InvoicePayment>
 */
class InvoicePaymentFactory extends Factory
{
    protected $model = InvoicePayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'amount' => fn (array $attributes) => Invoice::query()->findOrFail($attributes['invoice_id'])->total,
            'payment_type' => (new CompanyTransaction)->getMorphClass(),
            'payment_id' => fn (array $attributes) => $this->payment(Invoice::query()->findOrFail($attributes['invoice_id']), $attributes['amount'])->id,
        ];
    }

    private function payment(Invoice $invoice, string|float $amount): CompanyTransaction
    {
        $purchase = $invoice->direction === InvoiceDirection::Purchase;

        return CompanyTransaction::query()->create([
            'party_id' => $invoice->party_id,
            'role' => $purchase ? PartyRole::Supplier : PartyRole::Customer,
            'occurred_on' => $invoice->issued_on,
            'debit' => $purchase ? $amount : 0,
            'credit' => $purchase ? 0 : $amount,
            'payment_method' => PaymentMethod::Cash,
        ]);
    }
}
