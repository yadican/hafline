<?php

namespace Tests\Feature\Admin;

use App\Enums\CashDirection;
use App\Models\CashCategory;
use App\Models\Invoice;
use App\Models\InvoiceAttachment;
use App\Models\Party;
use App\Models\User;
use App\Services\Ledger\Ledger;
use App\Services\PartyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceFormTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_create_form_offers_categories_by_direction_and_hides_system_parties(): void
    {
        $expense = CashCategory::factory()->create(['direction' => CashDirection::Expense]);
        $income = CashCategory::factory()->create(['direction' => CashDirection::Income]);
        $temporary = app(PartyService::class)->temporaryParty();

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.create'))
            ->assertOk()
            ->assertViewHas('options', fn (array $options): bool => array_key_exists($expense->id, $options['categories']['purchase'])
                && ! array_key_exists($income->id, $options['categories']['purchase'])
                && array_key_exists($income->id, $options['categories']['sale'])
                && ! array_key_exists($expense->id, $options['categories']['sale'])
                && ! array_key_exists($temporary->id, $options['parties']));
    }

    public function test_store_saves_the_invoice_with_its_lines_and_posts_it_to_the_party(): void
    {
        $supplier = Party::factory()->company()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), $this->payload($supplier, [
                ['description' => 'Çimento', 'quantity' => '10', 'unit_price' => '250', 'vat_rate' => '20'],
                ['description' => 'Nakliye', 'quantity' => '1', 'unit_price' => '500', 'vat_rate' => '0'],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.invoices.index'))
            ->assertSessionHas('success', 'Fatura kaydedildi.');

        $invoice = Invoice::query()->sole();
        $this->assertSame(['A-1023', '3000.00', '500.00', '3500.00'], [$invoice->invoice_no, $invoice->subtotal, $invoice->vat_amount, $invoice->total]);
        $this->assertSame(['Çimento', 'Nakliye'], $invoice->lines()->pluck('description')->all());
        $this->assertSame($this->admin->id, $invoice->created_by);
        $this->assertSame('3500.00', app(Ledger::class)->live($invoice)->sole()->credit);
    }

    public function test_edit_form_shows_the_lines_and_update_replaces_them(): void
    {
        $invoice = Invoice::factory()->create(['invoice_no' => 'A-1023', 'subtotal' => 1000]);
        $invoice->lines()->update(['description' => 'Eski kalem']);

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.edit', $invoice))
            ->assertOk()
            ->assertSee('Eski kalem')
            ->assertSee('A-1023');

        $this->actingAs($this->admin)
            ->put(route('admin.invoices.update', $invoice), $this->payload($invoice->party, [
                ['description' => 'Kum', 'quantity' => '4', 'unit_price' => '150', 'vat_rate' => '0'],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.invoices.index'))
            ->assertSessionHas('success', 'Fatura güncellendi.');

        $invoice->refresh();
        $this->assertSame(['Kum'], $invoice->lines()->pluck('description')->all());
        $this->assertSame('600.00', $invoice->total);
        $this->assertSame('600.00', app(Ledger::class)->live($invoice)->sole()->credit);
    }

    public function test_invalid_line_returns_to_the_form_with_its_error_and_the_entered_lines(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.invoices.create'))
            ->post(route('admin.invoices.store'), $this->payload(Party::factory()->company()->create(), [
                ['description' => 'Kum 0-3 mm', 'quantity' => '0', 'unit_price' => '150'],
            ]))
            ->assertRedirect(route('admin.invoices.create'))
            ->assertSessionHasErrors(['lines.0.quantity' => 'miktar 0 değerinden büyük olmalıdır.']);

        $this->assertDatabaseCount('invoices', 0);

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.create'))
            ->assertSee('Kum 0-3 mm');
    }

    public function test_invoice_without_lines_shows_the_lines_message(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.invoices.create'))
            ->followingRedirects()
            ->post(route('admin.invoices.store'), $this->payload(Party::factory()->company()->create(), [
                ['description' => '', 'quantity' => '', 'unit_price' => ''],
            ]))
            ->assertSee('En az bir fatura satırı girin.');
    }

    public function test_files_chosen_on_the_create_form_are_saved_with_the_invoice(): void
    {
        Storage::fake('data');

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.store'), [
                ...$this->payload(Party::factory()->company()->create(), [['description' => 'Kum', 'quantity' => '1', 'unit_price' => '100']]),
                'attachments' => [
                    UploadedFile::fake()->image('fatura.jpg'),
                    UploadedFile::fake()->createWithContent('fatura.pdf', "%PDF-1.4\n%%EOF\n"),
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Fatura kaydedildi.');

        $attachments = Invoice::query()->sole()->attachments()->get();
        $this->assertCount(2, $attachments);
        $this->assertSame([false, true], $attachments->map->isPdf()->sort()->values()->all());
        $this->assertSame([$this->admin->id], $attachments->pluck('created_by')->unique()->all());
        $attachments->each(fn (InvoiceAttachment $attachment) => Storage::disk('data')->assertExists($attachment->path));
    }

    public function test_an_invalid_file_on_the_form_saves_nothing_and_keeps_the_entered_invoice(): void
    {
        Storage::fake('data');

        $this->actingAs($this->admin)
            ->from(route('admin.invoices.create'))
            ->post(route('admin.invoices.store'), [
                ...$this->payload(Party::factory()->company()->create(), [['description' => 'Kum', 'quantity' => '1', 'unit_price' => '100']]),
                'attachments' => [UploadedFile::fake()->createWithContent('sahte.pdf', 'PDF degil')],
            ])
            ->assertRedirect(route('admin.invoices.create'))
            ->assertSessionHasErrors(['attachments.0' => 'Geçersiz PDF dosyası.'])
            ->assertSessionHasInput('invoice_no', 'A-1023');

        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame([], Storage::disk('data')->allFiles());
    }

    public function test_edit_form_adds_files_only_up_to_the_invoice_limit(): void
    {
        Storage::fake('data');
        $invoice = Invoice::factory()->create();
        InvoiceAttachment::factory()->count(3)->create(['invoice_id' => $invoice]);
        $payload = $this->payload($invoice->party, [['description' => 'Kum', 'quantity' => '1', 'unit_price' => '100']]);

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.edit', $invoice))
            ->assertSee('name="attachments[]"', false);

        $this->actingAs($this->admin)
            ->put(route('admin.invoices.update', $invoice), [...$payload, 'attachments' => [UploadedFile::fake()->image('bir.jpg'), UploadedFile::fake()->image('iki.jpg')]])
            ->assertSessionHasErrors(['attachments' => 'Bu faturaya en fazla 1 dosya daha eklenebilir.']);

        $this->actingAs($this->admin)
            ->put(route('admin.invoices.update', $invoice), [...$payload, 'attachments' => [UploadedFile::fake()->image('bir.jpg')]])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Fatura güncellendi.');

        $this->assertSame(4, $invoice->attachments()->count());
        $this->assertSame(1, $invoice->attachments()->where('created_by', $this->admin->id)->count());

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.edit', $invoice))
            ->assertDontSee('name="attachments[]"', false)
            ->assertSee('Bu faturada en fazla 4 dosya olabilir');
    }

    public function test_creating_and_editing_require_their_permissions(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['view-dashboard', 'view-invoice']);
        $invoice = Invoice::factory()->create();
        $payload = $this->payload($invoice->party, [['description' => 'Kum', 'quantity' => '1', 'unit_price' => '10']]);

        $this->actingAs($viewer)->get(route('admin.invoices.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.invoices.store'), [...$payload, 'invoice_no' => 'A-2'])->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.invoices.edit', $invoice))->assertForbidden();
        $this->actingAs($viewer)->put(route('admin.invoices.update', $invoice), $payload)->assertForbidden();

        $this->assertDatabaseCount('invoices', 1);
    }

    /**
     * @param  list<array<string, string>>  $lines
     * @return array<string, mixed>
     */
    private function payload(Party $party, array $lines): array
    {
        return [
            'direction' => 'purchase',
            'party_id' => $party->id,
            'invoice_no' => 'A-1023',
            'issued_on' => today()->toDateString(),
            'lines' => $lines,
        ];
    }
}
