<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceAttachment>
 */
class InvoiceAttachmentFactory extends Factory
{
    protected $model = InvoiceAttachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'path' => 'invoice-attachments/'.now()->format('Y/m/d/H').'/'.fake()->uuid().'.jpg',
            'created_by' => null,
        ];
    }

    public function pdf(): static
    {
        return $this->state(fn (): array => [
            'path' => 'invoice-attachments/'.now()->format('Y/m/d/H').'/'.fake()->uuid().'.pdf',
        ]);
    }
}
