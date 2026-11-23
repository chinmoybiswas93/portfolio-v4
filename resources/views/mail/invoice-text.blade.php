{!! $messageText !!}

Invoice: {!! $number !!}
Client: {!! $client !!}
@if ($reference)
For: {!! $reference !!}
@endif
Issued: {!! $issued !!}
Due: {!! $due !!}
Amount due: {!! $amount !!} {!! $currency !!}

View invoice (secure link, valid for 30 days):
{!! $viewUrl !!}

The PDF is attached.
@if ($methods !== [])
How to pay: {!! implode(', ', $methods) !!}. Full details are on the invoice. Please quote {!! $number !!}.
@endif

{!! $sellerName !!}@if ($sellerTitle) · {!! $sellerTitle !!}@endif

{!! $sellerEmail !!}
