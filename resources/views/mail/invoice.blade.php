{{-- Invoice email (canvas "Invoice-Email"). Inline styles only: mail clients ignore stylesheets. --}}
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Invoice {{ $number }}</title></head>
<body style="margin:0;padding:24px 0;background:#F1F5F9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#334155;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#FFFFFF;border:1px solid #E2E8F0;border-radius:10px;">
    <tr><td style="padding:20px 28px;border-bottom:1px solid #E2E8F0;font-weight:600;color:#0F172A;font-size:16px;">{{ $sellerName }}</td></tr>
    <tr><td style="padding:24px 28px;font-size:15px;line-height:1.6;">
        <p style="margin:0 0 16px;white-space:pre-line;">{{ $messageText }}</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E2E8F0;border-radius:8px;">
            <tr><td style="padding:14px 16px;">
                <p style="margin:0 0 4px;font-size:12px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#64748B;">Amount due</p>
                <p style="margin:0 0 10px;font-size:26px;font-weight:600;color:#0F172A;">{{ $amount }} <span style="font-size:13px;font-weight:400;color:#64748B;">{{ $currency }}</span></p>
                <table role="presentation" cellpadding="0" cellspacing="0" style="font-size:14px;">
                    <tr><td style="padding:3px 16px 3px 0;color:#64748B;">Invoice</td><td style="color:#0F172A;">{{ $number }}</td></tr>
                    <tr><td style="padding:3px 16px 3px 0;color:#64748B;">Client</td><td style="color:#0F172A;">{{ $client }}</td></tr>
                    @if ($reference)<tr><td style="padding:3px 16px 3px 0;color:#64748B;">For</td><td style="color:#0F172A;">{{ $reference }}</td></tr>@endif
                    <tr><td style="padding:3px 16px 3px 0;color:#64748B;">Issued</td><td style="color:#0F172A;">{{ $issued }}</td></tr>
                    <tr><td style="padding:3px 16px 3px 0;color:#64748B;">Due</td><td style="color:#0F172A;">{{ $due }}</td></tr>
                </table>
            </td></tr>
        </table>
        <p style="margin:22px 0 4px;text-align:center;"><a href="{{ $viewUrl }}" style="display:inline-block;padding:11px 22px;border-radius:8px;background:#0F766E;color:#FFFFFF;font-weight:600;text-decoration:none;">View invoice</a></p>
        <p style="margin:0 0 20px;text-align:center;font-size:12px;color:#64748B;">Secure link to this invoice's PDF, valid for 30 days. The PDF is also attached.</p>
        @if ($methods !== [])
            <p style="margin:0 0 4px;font-size:12px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#64748B;">How to pay</p>
            <p style="margin:0 0 20px;font-size:14px;">{{ implode(' · ', $methods) }}. Full details are on the invoice. Please quote {{ $number }}.</p>
        @endif
    </td></tr>
    <tr><td style="padding:16px 28px;background:#F8FAFC;border-top:1px solid #E2E8F0;font-size:13px;color:#64748B;border-radius:0 0 10px 10px;">
        {{ $sellerName }}@if ($sellerTitle) · {{ $sellerTitle }}@endif<br>
        @if ($sellerEmail)<a href="mailto:{{ $sellerEmail }}" style="color:#0F766E;">{{ $sellerEmail }}</a> · @endif{{ $siteHost }}<br>
        Reply to this email with any questions about this invoice.
    </td></tr>
</table>
</td></tr></table>
</body>
</html>
