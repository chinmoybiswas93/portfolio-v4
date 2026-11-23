{{-- Preview PDF (canvas "Admin-Invoice-Preview"): versions on the left, the selected version inline on the right. --}}
@php
    /** @var \App\Models\Invoice $invoice */
    $versions = $invoice->pdfs()->get();
    $url = fn ($pdf, bool $download = false) => route('filament.admin.invoices.pdf', ['invoice' => $invoice, 'pdf' => $pdf]).($download ? '?download=1' : '');
@endphp
<div x-data="{ src: @js($versions->isNotEmpty() ? $url($versions->first()) : '') }" class="cb-pdf-preview">
    <aside class="cb-pdf-versions">
        <p class="cb-pdf-versions-title">Versions</p>
        <ul>
            @foreach ($versions as $pdf)
                <li>
                    <button type="button" x-on:click="src = @js($url($pdf))" x-bind:aria-pressed="src === @js($url($pdf))" class="cb-pdf-version">
                        <span class="cb-pdf-version-head">{{ $pdf->label() }} @if ($loop->first)<x-filament::badge size="sm">Current</x-filament::badge>@endif</span>
                        <span>{{ $pdf->created_at?->format('j M Y, H:i') }} · {{ $pdf->status_at_render->getLabel() }}</span>
                    </button>
                    <a href="{{ $url($pdf, true) }}" class="cb-pdf-download">Download</a>
                </li>
            @endforeach
        </ul>
        <p class="cb-pdf-note">Versions are never overwritten. Regenerating adds a new one.</p>
    </aside>
    <iframe x-bind:src="src" title="Invoice PDF preview" class="cb-pdf-frame"></iframe>
</div>
