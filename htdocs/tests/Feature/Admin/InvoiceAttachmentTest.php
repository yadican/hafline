<?php

namespace Tests\Feature\Admin;

use App\Models\Invoice;
use App\Models\InvoiceAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('data');
        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_uploaded_pictures_are_downscaled_kept_and_shown_on_the_detail(): void
    {
        $invoice = Invoice::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.attachments.store', $invoice), ['attachments' => [
                UploadedFile::fake()->image('on.jpg', 2400, 1100),
                UploadedFile::fake()->image('arka.png', 800, 400),
            ]])
            ->assertRedirect(route('admin.invoices.show', $invoice))
            ->assertSessionHas('success', 'Dosya eklendi.');

        $attachments = $invoice->attachments()->get();
        $this->assertCount(2, $attachments);
        Storage::disk('data')->assertExists($attachments[0]->path);
        $this->assertStringStartsWith('invoice-attachments/', $attachments[0]->path);
        $this->assertSame(1600, getimagesize(Storage::disk('data')->path($attachments[0]->path))[0]);
        $this->assertSame($this->admin->id, $attachments[0]->created_by);

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('src="'.route('admin.invoices.attachments.show', [$invoice, $attachments[0]]).'"', false)
            ->assertSee('action="'.route('admin.invoices.attachments.destroy', [$invoice, $attachments[1]]).'"', false);

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.attachments.show', [$invoice, $attachments[0]]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_list_marks_invoices_that_have_pictures(): void
    {
        InvoiceAttachment::factory()->create(['invoice_id' => Invoice::factory()->create(['invoice_no' => 'RESIMLI-1'])]);
        Invoice::factory()->create(['invoice_no' => 'RESIMSIZ-1']);

        $response = $this->actingAs($this->admin)->get(route('admin.invoices.index'))->assertOk();

        $this->assertStringContainsString('fa-paperclip', $this->cell($response->getContent(), 'RESIMLI-1'));
        $this->assertStringNotContainsString('fa-paperclip', $this->cell($response->getContent(), 'RESIMSIZ-1'));
    }

    public function test_only_users_who_can_see_invoices_open_a_picture(): void
    {
        $attachment = $this->storedAttachment();

        $outsider = User::factory()->create();
        $outsider->givePermissionTo('view-dashboard');

        $this->actingAs($outsider)
            ->get(route('admin.invoices.attachments.show', [$attachment->invoice, $attachment]))
            ->assertForbidden();

        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['view-dashboard', 'view-invoice']);

        $this->actingAs($viewer)
            ->get(route('admin.invoices.attachments.show', [$attachment->invoice, $attachment]))
            ->assertOk();
    }

    public function test_a_picture_is_reachable_only_through_its_own_invoice(): void
    {
        $attachment = $this->storedAttachment();

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.attachments.show', [Invoice::factory()->create(), $attachment]))
            ->assertNotFound();
    }

    public function test_viewer_cannot_upload_or_delete_pictures(): void
    {
        $attachment = $this->storedAttachment();
        $invoice = $attachment->invoice;
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['view-dashboard', 'view-invoice']);

        $this->actingAs($viewer)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee('action="'.route('admin.invoices.attachments.store', $invoice).'"', false)
            ->assertDontSee('action="'.route('admin.invoices.attachments.destroy', [$invoice, $attachment]).'"', false);

        $this->actingAs($viewer)
            ->post(route('admin.invoices.attachments.store', $invoice), ['attachments' => [UploadedFile::fake()->image('fatura.jpg')]])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->delete(route('admin.invoices.attachments.destroy', [$invoice, $attachment]))
            ->assertForbidden();

        $this->assertSame(1, $invoice->attachments()->count());
    }

    public function test_a_pdf_is_kept_as_uploaded_and_opened_from_the_detail(): void
    {
        $invoice = Invoice::factory()->create(['invoice_no' => 'A-1023']);
        $pdf = $this->pdf();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.attachments.store', $invoice), ['attachments' => [$pdf]])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Dosya eklendi.');

        $attachment = $invoice->attachments()->sole();
        $this->assertStringEndsWith('.pdf', $attachment->path);
        $this->assertSame($pdf->getContent(), Storage::disk('data')->get($attachment->path));

        $url = route('admin.invoices.attachments.show', [$invoice, $attachment]);

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('href="'.$url.'"', false)
            ->assertSee("PDF'i aç", false)
            ->assertDontSee('src="'.$url.'"', false);

        $this->actingAs($this->admin)
            ->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="fatura-a-1023.pdf"');
    }

    public function test_a_file_that_is_neither_a_picture_nor_a_pdf_is_rejected(): void
    {
        $invoice = Invoice::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.attachments.store', $invoice), ['attachments' => [UploadedFile::fake()->create('fatura.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')]])
            ->assertSessionHasErrors(['attachments.0' => 'Fatura dosyası JPEG, PNG, WebP ya da PDF olmalı.']);

        $this->assertSame(0, $invoice->attachments()->count());
    }

    public function test_a_pdf_that_is_too_large_or_not_really_a_pdf_is_rejected(): void
    {
        $invoice = Invoice::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.attachments.store', $invoice), ['attachments' => [UploadedFile::fake()->create('buyuk.pdf', 11 * 1024, 'application/pdf')]])
            ->assertSessionHasErrors(['attachments.0' => 'PDF en fazla 10 MB olabilir.']);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.attachments.store', $invoice), ['attachments' => [UploadedFile::fake()->createWithContent('sahte.pdf', '<html>fatura</html>')]])
            ->assertSessionHasErrors(['attachments.0' => 'Geçersiz PDF dosyası.']);

        $this->assertSame(0, $invoice->attachments()->count());
        $this->assertSame([], Storage::disk('data')->allFiles());
    }

    public function test_an_invoice_keeps_at_most_four_pictures(): void
    {
        $invoice = Invoice::factory()->create();
        InvoiceAttachment::factory()->count(3)->create(['invoice_id' => $invoice]);

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.attachments.store', $invoice), ['attachments' => [
                UploadedFile::fake()->image('bir.jpg'),
                UploadedFile::fake()->image('iki.jpg'),
            ]])
            ->assertSessionHasErrors(['attachments' => 'Bu faturaya en fazla 1 dosya daha eklenebilir.']);

        $this->assertSame(3, $invoice->attachments()->count());

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.attachments.store', $invoice), ['attachments' => [UploadedFile::fake()->image('bir.jpg')]])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertDontSee('action="'.route('admin.invoices.attachments.store', $invoice).'"', false);
    }

    public function test_deleting_a_picture_removes_it_from_the_invoice(): void
    {
        $attachment = $this->storedAttachment();
        $invoice = $attachment->invoice;

        $this->actingAs($this->admin)
            ->delete(route('admin.invoices.attachments.destroy', [$invoice, $attachment]))
            ->assertRedirect(route('admin.invoices.show', $invoice))
            ->assertSessionHas('success', 'Dosya silindi.');

        $this->assertSoftDeleted($attachment);
        $this->assertSame(0, $invoice->attachments()->count());
    }

    private function storedAttachment(): InvoiceAttachment
    {
        $attachment = InvoiceAttachment::factory()->create();
        Storage::disk('data')->put($attachment->path, UploadedFile::fake()->image('fatura.jpg')->getContent());

        return $attachment;
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('fatura.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
    }

    private function cell(string $html, string $invoiceNo): string
    {
        $start = strpos($html, $invoiceNo);
        $this->assertNotFalse($start);

        return substr($html, $start, (int) strpos($html, '</td>', $start) - $start);
    }
}
