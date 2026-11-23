@php
    /*
     * Standard table toolbar (canvas "Admin-Invoices"): status tabs and filters inline on the left of the
     * toolbar, search on the right. Rendered into every admin table through TablesRenderHook::TOOLBAR_START.
     */
    $component = \Livewire\Livewire::current();
    $tabs = in_array(\Filament\Resources\Concerns\HasTabs::class, class_uses_recursive($component), true) ? $component->getCachedTabs() : [];
    $filters = $component instanceof \Filament\Tables\Contracts\HasTable
        ? collect($component->getTable()->getFilters())->filter(fn ($f): bool => $f instanceof \Filament\Tables\Filters\SelectFilter && ! $f->isMultiple())
        : collect();
@endphp
@if ($tabs !== [] || $filters->isNotEmpty())
    <div class="cb-toolbar">
        @if ($tabs !== [])
            <div class="cb-tabs" role="tablist" aria-label="Filter by status">
                @foreach ($tabs as $key => $tab)
                    @php
                        $selected = (string) $component->activeTab === (string) $key;
                    @endphp
                    <button type="button" role="tab" aria-selected="{{ $selected ? 'true' : 'false' }}"
                        wire:click="$set('activeTab', @js((string) $key))" @class(['cb-tab', 'cb-tab-active' => $selected])>
                        {{ $tab->getLabel() ?? \Illuminate\Support\Str::headline((string) $key) }}
                    </button>
                @endforeach
            </div>
        @endif

        @foreach ($filters as $filter)
            @php
                $options = $filter instanceof \Filament\Tables\Filters\TernaryFilter
                    ? ['1' => $filter->getTrueLabel(), '0' => $filter->getFalseLabel()]
                    : $filter->getOptions();
                $placeholder = $filter instanceof \Filament\Tables\Filters\TernaryFilter ? $filter->getPlaceholder() : ($filter->getPlaceholder() ?: 'All');
                $id = 'cb-filter-'.$filter->getName();
            @endphp
            <label class="cb-filter" for="{{ $id }}">
                <span>{{ $filter->getLabel() }}</span>
                <select id="{{ $id }}" wire:model.live="tableFilters.{{ $filter->getName() }}.value">
                    <option value="">{{ $placeholder }}</option>
                    @foreach ($options as $value => $label)
                        <option value="{{ $value }}">{{ $label instanceof \Filament\Support\Contracts\HasLabel ? $label->getLabel() : $label }}</option>
                    @endforeach
                </select>
            </label>
        @endforeach
    </div>
@endif
