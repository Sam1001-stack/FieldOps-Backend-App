<?php

namespace App\Http\Api\V1;

use App\Application\Invoicing\CreateInvoiceFromJob;
use App\Application\Invoicing\SendInvoice;
use App\Domain\Invoicing\Invoice;
use App\Domain\Jobs\ServiceJob;
use App\Http\Resources\InvoiceResource;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);
        $user = $request->user();
        $query = Invoice::query()->with(['customer', 'job', 'items', 'organization.settings']);

        if ($user->roleIn() === \App\Enums\UserRole::Customer) {
            $query->whereHas('customer', fn ($q) => $q->where('user_id', $user->id));
        }

        return InvoiceResource::collection($query->latest()->paginate(50));
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        return new InvoiceResource($invoice->load(['items', 'customer', 'job', 'organization.settings']));
    }

    public function fromJob(Request $request, ServiceJob $job, CreateInvoiceFromJob $action)
    {
        $this->authorize('invoice', $job);
        $invoice = $action->handle($job, $request->user());

        return new InvoiceResource($invoice->load(['items', 'customer', 'job', 'organization.settings']));
    }

    public function send(Request $request, Invoice $invoice, SendInvoice $action)
    {
        $this->authorize('update', $invoice);
        $invoice = $action->handle($invoice, $request->user());

        return new InvoiceResource($invoice->load(['items', 'customer', 'job', 'organization.settings']));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorize('export', Invoice::class);
        $invoices = Invoice::query()->with('customer')->get();

        return response()->streamDownload(function () use ($invoices): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Umsatz', 'Rechnungsnr', 'Datum', 'Kunde', 'Betrag', 'Status'], ';');
            foreach ($invoices as $invoice) {
                fputcsv($out, [
                    '4000',
                    $invoice->number,
                    $invoice->issued_at?->format('d.m.Y'),
                    $invoice->customer?->name,
                    number_format($invoice->total_cents / 100, 2, ',', '.'),
                    $invoice->status->value,
                ], ';');
            }
            fclose($out);
        }, 'datev-export.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function destroy(Invoice $invoice)
    {
        $this->authorize('update', $invoice);
        abort_unless($invoice->status === \App\Enums\InvoiceStatus::Draft, 422, 'GoBD: versendete Rechnungen bleiben erhalten.');
        $invoice->delete();

        return response()->json(['message' => 'Entwurf gelöscht.']);
    }
}
