@php /** @var \App\Models\McpAuditLog $log */ @endphp
<dl class="grid grid-cols-[8rem_1fr] gap-x-4 gap-y-2 text-sm">
    <dt class="text-gray-500">App</dt><dd>{{ $log->client_name ?? '—' }} <span class="text-gray-500">({{ $log->client_id ?? '—' }})</span></dd>
    <dt class="text-gray-500">Tool</dt><dd><code>{{ $log->tool }}</code> · {{ $log->scope ?? '—' }}</dd>
    <dt class="text-gray-500">Target</dt><dd>{{ trim(($log->target_type ?? '').' '.($log->target_id ?? '')) ?: '—' }} {{ $log->target_label }}</dd>
    <dt class="text-gray-500">Result</dt><dd>{{ $log->result }} (HTTP {{ $log->http_status }})@if ($log->error) · {{ $log->error }}@endif</dd>
    <dt class="text-gray-500">From</dt><dd>{{ $log->ip }} · {{ $log->user_agent }}</dd>
    <dt class="text-gray-500">Changes</dt>
    <dd><pre class="whitespace-pre-wrap break-words rounded-lg bg-gray-950/40 p-3 text-xs">{{ $log->changes ? json_encode($log->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '—' }}</pre></dd>
</dl>
