@php /** @var \App\Domain\Invoicing\InvoiceDocument $doc */ @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $doc->number }}</title>
    <style>
        @page { margin: 48px 56px 72px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; line-height: 1.5; color: #334155; margin: 0; }
        table { border-collapse: collapse; }
        p { margin: 0; }
        .ink { color: #0F172A; }
        .muted { color: #475569; }
        .dim { color: #5B6B80; }
        .label { font-size: 8.5px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #5B6B80; }
        .accent { height: 2px; width: 48px; background: #0F766E; margin: 22px 0 20px; }
        .lines th { padding: 7px 0; border-bottom: 1px solid #CBD5E1; font-size: 8.5px; font-weight: bold; letter-spacing: 0.8px; text-transform: uppercase; color: #5B6B80; }
        .lines td { padding: 9px 0; border-bottom: 1px solid #E2E8F0; vertical-align: top; }
        .num { text-align: right; }
        .totals td { padding: 4px 0; }
        .status { display: inline-block; padding: 2px 8px; border: 1px solid #99D8CF; background: #F0FDFA; color: #0F766E; font-size: 8.5px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .payment { width: 100%; margin-top: 28px; background: #F8FAFC; border: 1px solid #E2E8F0; }
        .payment td { padding: 0 14px 12px; vertical-align: top; font-size: 9.5px; }
        footer { position: fixed; left: 0; right: 0; bottom: -44px; border-top: 1px solid #E2E8F0; padding-top: 8px; font-size: 9px; color: #5B6B80; }
    </style>
</head>
<body>
    <footer>
        <table style="width: 100%;">
            <tr>
                <td>{{ $doc->footer }}</td>
                <td style="width: 80px;">{{-- page number drawn by GenerateInvoicePdf --}}</td>
            </tr>
        </table>
    </footer>

    <table style="width: 100%;">
        <tr>
            <td style="vertical-align: top;">
                <table>
                    <tr>
                        <td style="vertical-align: middle; padding-right: 10px;"><img src="{{ $logo }}" width="28" height="32" alt=""></td>
                        <td style="vertical-align: middle;">
                            <p class="ink" style="font-size: 14px; font-weight: bold;">{{ $doc->seller['name'] ?? '' }}</p>
                            <p class="muted" style="font-size: 9.5px;">{{ $doc->seller['title'] ?? '' }}</p>
                        </td>
                    </tr>
                </table>
                <p class="muted" style="margin-top: 14px; font-size: 9.5px; line-height: 1.4;">
                    @if (! empty($doc->seller['business_name'])){{ $doc->seller['business_name'] }}<br>@endif
                    @if (! empty($doc->seller['address'])){!! nl2br(e($doc->seller['address'])) !!}<br>@endif
                    {{ $doc->seller['email'] ?? '' }}
                    @if (! empty($doc->seller['tax_id']))<br>Tax ID: {{ $doc->seller['tax_id'] }}@endif
                </p>
            </td>
            <td style="vertical-align: top; text-align: right;">
                <p class="ink" style="font-size: 21px; font-weight: bold; letter-spacing: 2px;">INVOICE</p>
                <p class="ink" style="margin-top: 4px; font-size: 11px; font-weight: bold;">{{ $doc->number }}</p>
                <p style="margin-top: 8px;"><span class="status">{{ $doc->status }}</span></p>
                <table style="margin: 10px 0 0 auto; font-size: 9.5px;">
                    <tr><td class="dim" style="padding: 1px 14px 1px 0; text-align: left;">Issue date</td><td class="ink" style="text-align: right;">{{ $doc->issueDate }}</td></tr>
                    <tr><td class="dim" style="padding: 1px 14px 1px 0; text-align: left;">Due date</td><td class="ink" style="text-align: right;">{{ $doc->dueDate }}</td></tr>
                    <tr><td class="dim" style="padding: 1px 14px 1px 0; text-align: left;">Currency</td><td class="ink" style="text-align: right;">{{ $doc->currency }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="accent"></div>

    <table style="width: 100%;">
        <tr>
            <td style="vertical-align: top; width: 55%;">
                <p class="label" style="margin-bottom: 5px;">Bill to</p>
                <p class="ink" style="font-size: 11px; font-weight: bold;">{{ $doc->client['company_name'] ?? '' }}</p>
                <p class="muted" style="margin-top: 2px; font-size: 9.5px; line-height: 1.4;">
                    @if (! empty($doc->client['contact_name'])){{ $doc->client['contact_name'] }}<br>@endif
                    @foreach ($doc->client['address_lines'] ?? [] as $addressLine){{ $addressLine }}<br>@endforeach
                    @if (! empty($doc->client['tax_id'])){{ $doc->client['tax_id_label'] ?? 'Tax ID' }}: {{ $doc->client['tax_id'] }}@endif
                </p>
            </td>
            <td style="vertical-align: top;">
                @if ($doc->reference)
                    <p class="label" style="margin-bottom: 5px;">Project</p>
                    <p class="ink" style="font-size: 10.5px;">{{ $doc->reference }}</p>
                @endif
            </td>
        </tr>
    </table>

    <table class="lines" style="width: 100%; margin-top: 22px;">
        <thead>
            <tr>
                <th style="text-align: left;">Description</th>
                <th class="num" style="width: 44px;">Qty</th>
                <th class="num" style="width: 84px;">Unit price</th>
                <th class="num" style="width: 84px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($doc->lines as $line)
                <tr>
                    <td style="padding-right: 10px;">
                        <span class="ink" style="font-weight: bold;">{{ $line['title'] }}</span>
                        @if ($line['note'] !== '')<br><span class="dim" style="font-size: 9px;">{!! nl2br(e($line['note'])) !!}</span>@endif
                    </td>
                    <td class="num">{{ $line['qty'] }}</td>
                    <td class="num">{{ $line['price'] }}</td>
                    <td class="num ink">{{ $line['amount'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="width: 100%; margin-top: 14px;">
        <tr>
            <td style="vertical-align: top; padding-right: 26px;">
                @if ($doc->notes)
                    <p class="label" style="margin-bottom: 4px;">Notes</p>
                    <p class="muted" style="font-size: 9.5px;">{!! nl2br(e($doc->notes)) !!}</p>
                @endif
            </td>
            <td style="vertical-align: top; width: 230px;">
                <table class="totals" style="width: 100%; font-size: 10px;">
                    @foreach ($doc->totals as $row)
                        <tr><td class="dim">{{ $row['label'] }}</td><td class="num ink">{{ $row['value'] }}</td></tr>
                    @endforeach
                    <tr>
                        <td class="ink" style="padding-top: 9px; border-top: 1px solid #0F172A; font-size: 11.5px; font-weight: bold;">Total due</td>
                        <td class="num ink" style="padding-top: 9px; border-top: 1px solid #0F172A; font-size: 15px; font-weight: bold;">{{ $doc->totalDue }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($doc->payment !== [])
        <table class="payment">
            <tr><td colspan="{{ count($doc->payment) }}" style="padding-top: 12px;"><p class="label">Payment details — please quote {{ $doc->number }}</p></td></tr>
            <tr>
                @foreach ($doc->payment as $method)
                    <td>
                        <p class="ink" style="font-weight: bold;">{{ $method['label'] }}</p>
                        <p class="muted" style="margin-top: 2px;">{!! nl2br(e($method['text'])) !!}</p>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif
</body>
</html>
