<?php

namespace App\Application\Invoicing;

use App\Domain\Identity\User;
use App\Domain\Invoicing\Invoice;
use App\Domain\Jobs\JobStatusEvent;
use App\Enums\InvoiceStatus;
use App\Enums\JobStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use Illuminate\Support\Facades\Storage;

final class SendInvoice
{
    public function handle(Invoice $invoice, User $actor): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new DomainException('GoBD: versendete Rechnungen sind unveränderbar.');
        }

        $settings = $invoice->organization->settings;
        $number = sprintf('%s-%s-%04d', $settings->invoice_prefix ?? 'RE', now('Europe/Berlin')->year, $settings->next_invoice_number);
        $settings->increment('next_invoice_number');

        $invoice->load(['items', 'customer', 'job.site', 'organization.settings']);

        $pdf = Pdf::loadView('invoices.pdf', ['invoice' => $invoice, 'settings' => $invoice->organization->settings]);
        $path = "invoices/{$invoice->organization_id}/{$number}.pdf";
        Storage::disk('public')->put($path, $pdf->output());

        $invoice->update([
            'number' => $number,
            'status' => InvoiceStatus::Sent,
            'issued_at' => now('Europe/Berlin')->toDateString(),
            'sent_at' => now(),
            'pdf_path' => $path,
        ]);

        $job = $invoice->job;
        $from = $job->status;
        $job->status = JobStatus::Invoiced;
        $job->save();

        JobStatusEvent::query()->create([
            'organization_id' => $job->organization_id,
            'job_id' => $job->id,
            'user_id' => $actor->id,
            'from_status' => $from->value,
            'to_status' => JobStatus::Invoiced->value,
        ]);

        activity()->causedBy($actor)->performedOn($invoice)->log('invoice.sent');

        return $invoice->refresh();
    }
}
