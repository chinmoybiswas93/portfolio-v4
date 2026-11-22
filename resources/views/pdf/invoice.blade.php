@php /** @var \App\Domain\Invoicing\InvoiceDocument $doc */ @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $doc->number }}</title>
    <style>
        /* Compact A4 in Inter (canvas v17). Fonts load from resources/fonts/inter; DomPDF never fetches remote files. */
        @font-face { font-family: 'Inter'; font-weight: 400; font-style: normal; src: url('{{ resource_path('fonts/inter/Inter-Regular.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Inter'; font-weight: 500; font-style: normal; src: url('{{ resource_path('fonts/inter/Inter-Medium.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Inter'; font-weight: 600; font-style: normal; src: url('{{ resource_path('fonts/inter/Inter-SemiBold.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Inter'; font-weight: 700; font-style: normal; src: url('{{ resource_path('fonts/inter/Inter-SemiBold.ttf') }}') format('truetype'); }
        @page { margin: 40px 44px 60px; }
        body { font-family: 'Inter', 'DejaVu Sans', sans-serif; font-size: 10px; line-height: 1.45; color: #334155; margin: 0; }
        table { border-collapse: collapse; }
        p { margin: 0; }
        .ink { color: #0F172A; }
        .muted { color: #475569; }
        .dim { color: #5B6B80; }
        .label { font-size: 8px; font-weight: 600; letter-spacing: 0.9px; text-transform: uppercase; color: #5B6B80; }
        .accent { height: 2px; width: 40px; background: #0F766E; margin: 16px 0 14px; }
        .lines th { padding: 5px 0; border-bottom: 1px solid #CBD5E1; font-size: 8px; font-weight: 600; letter-spacing: 0.7px; text-transform: uppercase; color: #5B6B80; }
        .lines td { padding: 6px 0; border-bottom: 1px solid #E2E8F0; vertical-align: top; }
        .num { text-align: right; }
        .totals td { padding: 2.5px 0; }
        .status { display: inline-block; padding: 1px 7px; border: 1px solid #99D8CF; background: #F0FDFA; color: #0F766E; font-size: 7.5px; font-weight: 600; letter-spacing: 0.9px; text-transform: uppercase; }
        .payment { width: 100%; margin-top: 18px; background: #F8FAFC; border: 1px solid #E2E8F0; }
        .payment td { padding: 0 12px 9px; vertical-align: top; font-size: 9px; }
        footer { position: fixed; left: 0; right: 0; bottom: -36px; border-top: 1px solid #E2E8F0; padding-top: 6px; font-size: 8px; color: #5B6B80; }
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
                        <td style="vertical-align: middle; padding-right: 8px;"><img src="{{ $logo }}" width="22" height="25" alt=""></td>
                        <td style="vertical-align: middle;">
                            <p class="ink" style="font-size: 12.5px; font-weight: 600;">{{ $doc->seller['name'] ?? '' }}</p>
                            <p class="muted" style="font-size: 8.5px;">{{ $doc->seller['title'] ?? '' }}</p>
                        </td>
                    </tr>
                </table>
                <p class="muted" style="margin-top: 9px; font-size: 8.5px; line-height: 1.45;">
                    @if (! empty($doc->seller['business_name'])){{ $doc->seller['business_name'] }}<br>@endif
                    @if (! empty($doc->seller['address'])){!! nl2br(e($doc->seller['address'])) !!}<br>@endif
                    {{ $doc->seller['email'] ?? '' }}
                    @if (! empty($doc->seller['tax_id']))<br>Tax ID: {{ $doc->seller['tax_id'] }}@endif
                </p>
            </td>
            <td style="vertical-align: top; text-align: right;">
                <p class="ink" style="font-size: 17px; font-weight: 600; letter-spacing: 1.6px;">INVOICE</p>
                <p class="ink" style="margin-top: 2px; font-size: 10px; font-weight: 600;">{{ $doc->number }}</p>
                <p style="margin-top: 5px;"><span class="status">{{ $doc->status }}</span></p>
                <table style="margin: 7px 0 0 auto; font-size: 8.5px;">
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
                <p class="ink" style="font-size: 10.5px; font-weight: 600;">{{ $doc->client['company_name'] ?? '' }}</p>
                <p class="muted" style="margin-top: 1px; font-size: 8.5px; line-height: 1.45;">
                    @if (! empty($doc->client['contact_name'])){{ $doc->client['contact_name'] }}<br>@endif
                    @foreach ($doc->client['address_lines'] ?? [] as $addressLine){{ $addressLine }}<br>@endforeach
                    @if (! empty($doc->client['tax_id'])){{ $doc->client['tax_id_label'] ?? 'Tax ID' }}: {{ $doc->client['tax_id'] }}@endif
                </p>
            </td>
            <td style="vertical-align: top;">
                @if ($doc->reference)
                    <p class="label" style="margin-bottom: 5px;">Project</p>
                    <p class="ink" style="font-size: 9.5px;">{{ $doc->reference }}</p>
                @endif
            </td>
        </tr>
    </table>

    <table class="lines" style="width: 100%; margin-top: 16px;">
        <thead>
            <tr>
                <th style="text-align: left;">Description</th>
                <th class="num" style="width: 40px;">Qty</th>
                <th class="num" style="width: 76px;">Unit price</th>
                <th class="num" style="width: 76px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($doc->lines as $line)
                <tr>
                    <td style="padding-right: 10px;">
                        <span class="ink" style="font-weight: 500;">{{ $line['title'] }}</span>
                        @if ($line['note'] !== '')<br><span class="dim" style="font-size: 8.5px;">{!! nl2br(e($line['note'])) !!}</span>@endif
                    </td>
                    <td class="num">{{ $line['qty'] }}</td>
                    <td class="num">{{ $line['price'] }}</td>
                    <td class="num ink">{{ $line['amount'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="width: 100%; margin-top: 10px;">
        <tr>
            <td style="vertical-align: top; padding-right: 26px;">
                @if ($doc->notes)
                    <p class="label" style="margin-bottom: 4px;">Notes</p>
                    <p class="muted" style="font-size: 8.5px;">{!! nl2br(e($doc->notes)) !!}</p>
                @endif
            </td>
            <td style="vertical-align: top; width: 210px;">
                <table class="totals" style="width: 100%; font-size: 9px;">
                    @foreach ($doc->totals as $row)
                        <tr><td class="dim">{{ $row['label'] }}</td><td class="num ink">{{ $row['value'] }}</td></tr>
                    @endforeach
                    <tr>
                        <td class="ink" style="padding-top: 6px; border-top: 1px solid #0F172A; font-size: 10.5px; font-weight: 600;">Total due</td>
                        <td class="num ink" style="padding-top: 6px; border-top: 1px solid #0F172A; font-size: 13px; font-weight: 600;">{{ $doc->totalDue }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($doc->payment !== [])
        <table class="payment">
            <tr><td colspan="{{ count($doc->payment) }}" style="padding-top: 9px;"><p class="label">Payment details — please quote {{ $doc->number }}</p></td></tr>
            <tr>
                @foreach ($doc->payment as $method)
                    <td>
                        <p class="ink" style="font-weight: 600;">{{ $method['label'] }}</p>
                        <p class="muted" style="margin-top: 1px;">{!! nl2br(e($method['text'])) !!}</p>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif
</body>
</html>
