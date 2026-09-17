<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1c2430; }
        h1 { font-size: 22px; margin: 0 0 8px; }
        .muted { color: #5c6570; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { border-bottom: 1px solid #e6e1d8; padding: 8px; text-align: left; }
        th { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        .right { text-align: right; }
        .total { font-size: 16px; font-weight: 700; }
        .stamp { margin-top: 28px; font-size: 11px; color: #047857; }
    </style>
</head>
<body>
    <h1>Rechnung {{ $invoice->number ?? 'ENTWURF' }}</h1>
    <p class="muted">Leistungsdatum {{ optional($invoice->service_date)->format('d.m.Y') }}</p>
    <p>
        <strong>{{ $settings->legal_name ?? $invoice->organization->name }}</strong><br>
        {{ $settings->street }}<br>
        {{ $settings->zip }} {{ $settings->city }}<br>
        Steuernummer {{ $settings->tax_number }} · USt-IdNr. {{ $settings->vat_id }}
    </p>
    <p>
        <strong>Kunde</strong><br>
        {{ $invoice->customer->name }}<br>
        {{ $invoice->job->site->street ?? '' }}<br>
        {{ $invoice->job->site->zip ?? '' }} {{ $invoice->job->site->city ?? '' }}
    </p>
    <table>
        <thead>
            <tr>
                <th>Beschreibung</th>
                <th class="right">Menge</th>
                <th class="right">Preis</th>
                <th class="right">MwSt</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($invoice->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="right">{{ $item->quantity }}</td>
                <td class="right">{{ number_format($item->unit_price_cents / 100, 2, ',', '.') }} €</td>
                <td class="right">{{ $item->tax_rate }}%</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <p class="right">Netto {{ number_format($invoice->subtotal_cents / 100, 2, ',', '.') }} €</p>
    <p class="right">MwSt {{ number_format($invoice->tax_cents / 100, 2, ',', '.') }} €</p>
    <p class="right total">Gesamt {{ number_format($invoice->total_cents / 100, 2, ',', '.') }} €</p>
    <p>IBAN {{ $settings->iban }} · BIC {{ $settings->bic }} · {{ $settings->bank_name }}</p>
    @if($invoice->status->value !== 'draft')
        <p class="stamp">GoBD: nicht mehr änderbar</p>
    @endif
</body>
</html>
