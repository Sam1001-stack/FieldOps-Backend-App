<?php

namespace App\Application\Invoicing;

use App\Domain\Identity\User;
use App\Domain\Invoicing\Invoice;
use App\Domain\Invoicing\InvoiceItem;
use App\Domain\Jobs\ServiceJob;
use App\Enums\InvoiceStatus;
use App\Enums\JobStatus;
use DomainException;

final class CreateInvoiceFromJob
{
    public function handle(ServiceJob $job, User $actor): Invoice
    {
        if ($job->status !== JobStatus::Completed) {
            throw new DomainException('Rechnung nur für erledigte Einsätze.');
        }

        if ($job->timeEntries()->whereNotNull('ended_at')->doesntExist() && $job->materials()->doesntExist()) {
            throw new DomainException('Einsatz braucht Zeit oder Material.');
        }

        if ($job->invoice()->exists()) {
            return $job->invoice()->firstOrFail();
        }

        $settings = $job->organization->settings;
        $hourly = $job->organization->catalogItems()->where('is_hourly_rate', true)->first();

        $invoice = Invoice::query()->create([
            'organization_id' => $job->organization_id,
            'job_id' => $job->id,
            'customer_id' => $job->customer_id,
            'status' => InvoiceStatus::Draft,
            'service_date' => now('Europe/Berlin')->toDateString(),
        ]);

        $subtotal = 0;
        $tax = 0;

        foreach ($job->timeEntries()->whereNotNull('ended_at')->get() as $entry) {
            $minutes = $entry->minutes();
            $hours = max(0.25, round($minutes / 60, 2));
            $rate = $hourly?->unit_price_cents ?? 7500;
            $line = (int) round($hours * $rate);
            $rateTax = $hourly?->tax_rate ?? 19;
            InvoiceItem::query()->create([
                'organization_id' => $job->organization_id,
                'invoice_id' => $invoice->id,
                'description' => "Arbeitszeit {$hours} h",
                'quantity' => $hours,
                'unit_price_cents' => $rate,
                'tax_rate' => $rateTax,
            ]);
            $subtotal += $line;
            $tax += (int) round($line * $rateTax / 100);
        }

        foreach ($job->materials as $material) {
            $line = (int) round((float) $material->quantity * $material->unit_price_cents);
            $itemTax = $material->catalogItem?->tax_rate ?? 19;
            InvoiceItem::query()->create([
                'organization_id' => $job->organization_id,
                'invoice_id' => $invoice->id,
                'description' => $material->catalogItem?->name ?? 'Material',
                'quantity' => $material->quantity,
                'unit_price_cents' => $material->unit_price_cents,
                'tax_rate' => $itemTax,
            ]);
            $subtotal += $line;
            $tax += (int) round($line * $itemTax / 100);
        }

        $invoice->update([
            'subtotal_cents' => $subtotal,
            'tax_cents' => $tax,
            'total_cents' => $subtotal + $tax,
        ]);

        activity()->causedBy($actor)->performedOn($invoice)->log('invoice.created');

        return $invoice->load('items');
    }
}
