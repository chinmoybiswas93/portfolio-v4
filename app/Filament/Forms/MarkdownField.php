<?php

declare(strict_types=1);

namespace App\Filament\Forms;

use App\Support\Markdown\MarkdownRenderer;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Support\Components\Attributes\Exposed;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Js;
use Illuminate\View\ComponentAttributeBag;
use Livewire\Attributes\Renderless;

/**
 * Markdown editor with Write / Preview tabs and a resizable height (ADR-0010).
 *
 * - Preview renders on the server with the site's own MarkdownRenderer and stylesheet, inside a sandboxed frame
 *   (no scripts, forms or navigation), in the admin's light or dark theme.
 * - The grip in the bottom-right corner (of the editor, or of the preview) sets the height of both (drag, or arrow keys
 *   when focused); the height is remembered per field in this browser. Height bindings use Alpine's object syntax: a string would replace the whole
 *   style attribute and drop x-show's display:none.
 */
class MarkdownField extends MarkdownEditor
{
    public const int MAX_PREVIEW_LENGTH = 200_000;

    protected int $defaultHeight = 240;

    public function defaultHeight(int $pixels): static
    {
        $this->defaultHeight = $pixels;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->extraAttributes(fn (): array => [
            'x-data' => '{ tab: \'write\' }',
            'x-show' => "tab === 'write'",
            'x-on:cb-md-tab.window' => 'if ($event.detail.path === '.Js::from($this->getStatePath()).') tab = $event.detail.tab',
        ]);
    }

    /**
     * One toolbar for every Markdown field (ADR-0010), so they all look and behave the same.
     *
     * @return array<array<string>>
     */
    public function getDefaultToolbarButtons(): array
    {
        return [
            ['bold', 'italic', 'strike', 'link'],
            ['heading'],
            ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
            ['table', 'attachFiles'],
            ['undo', 'redo'],
        ];
    }

    /**
     * The Write / Preview tabs sit at the right end of the toolbar row (so they show even when the label is hidden),
     * and the grip and preview sit right under the editor box, above any helper text (belowContent slot).
     * wrapInputHtml() is internal in Filament: MarkdownFieldTest fails if this stops rendering.
     */
    protected function wrapInputHtml(string $html, ?string $alpineDisabled = null, ?string $alpineValid = null, ?ComponentAttributeBag $attributes = null): string
    {
        $wrapped = parent::wrapInputHtml($html, $alpineDisabled, $alpineValid, $attributes);

        return $this->isDisabled() ? $wrapped : '<div class="cb-md">'.$this->tabsHtml().$wrapped.$this->belowEditorHtml().'</div>';
    }

    /** Called from the Preview tab. Returns a whole HTML document for the sandboxed frame. */
    #[Exposed]
    #[Renderless]
    public function renderPreview(?string $markdown = null, bool $dark = true): string
    {
        $markdown = mb_substr((string) $markdown, 0, self::MAX_PREVIEW_LENGTH);
        $body = app(MarkdownRenderer::class)->render($markdown);
        $css = e(Vite::asset('resources/css/app.css'));
        $theme = $dark ? 'dark' : 'light';
        $empty = '<p style="opacity:.7">Nothing to preview yet.</p>';

        return '<!doctype html><html lang="en" data-theme="'.$theme.'"><head><meta charset="utf-8">'
            .'<link rel="stylesheet" href="'.$css.'"><base target="_blank"></head>'
            .'<body style="margin:0;padding:16px 20px;background:var(--bg)"><div class="prose">'
            .($body === '' ? $empty : $body).'</div></body></html>';
    }

    private function tabsHtml(): string
    {
        $path = Js::from($this->getStatePath());

        $button = fn (string $tab, string $label): string => sprintf(
            '<button type="button" role="tab" class="cb-tab" :class="tab === \'%1$s\' && \'cb-tab-active\'" :aria-selected="tab === \'%1$s\'" x-on:click="$dispatch(\'cb-md-tab\', { path: %2$s, tab: \'%1$s\' })">%3$s</button>',
            $tab, e((string) $path), $label,
        );

        return '<div class="cb-tabs cb-md-tabs" role="tablist" aria-label="Editor mode" x-data="{ tab: \'write\' }" '
            .'x-on:cb-md-tab.window="if ($event.detail.path === '.e((string) $path).') tab = $event.detail.tab">'
            .$button('write', 'Write').$button('preview', 'Preview').'</div>';
    }

    private function belowEditorHtml(): string
    {
        $path = e((string) Js::from($this->getStatePath()));
        $height = $this->defaultHeight;

        return <<<HTML
            <div wire:ignore class="cb-md-below" x-data="{
                    tab: 'write', doc: '', loading: false, height: {$height}, cm: null, key: 'cb-md-height:' + {$path},
                    init() {
                        const saved = parseInt(localStorage.getItem(this.key) || '', 10);
                        if (saved >= 120 && saved <= 2000) this.height = saved;
                        const field = this.\$el.closest('.fi-fo-field');
                        const attach = () => {
                            const el = field && field.querySelector('.CodeMirror');
                            if (! el || ! el.CodeMirror) return false;
                            this.cm = el.CodeMirror; el.classList.add('cb-md-sized'); this.cm.setSize(null, this.height + 'px');
                            return true;
                        };
                        if (! attach()) { const timer = setInterval(() => attach() && clearInterval(timer), 100); setTimeout(() => clearInterval(timer), 10000); }
                    },
                    startResize(event) {
                        if (! this.cm) return;
                        const startY = event.clientY, startHeight = this.height;
                        const move = (e) => { this.height = Math.min(2000, Math.max(120, startHeight + e.clientY - startY)); this.cm.setSize(null, this.height + 'px'); };
                        const stop = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', stop); localStorage.setItem(this.key, this.height); };
                        window.addEventListener('pointermove', move); window.addEventListener('pointerup', stop);
                    },
                    nudge(delta) { if (! this.cm) return; this.height = Math.min(2000, Math.max(120, this.height + delta)); this.cm.setSize(null, this.height + 'px'); localStorage.setItem(this.key, this.height); },
                    async show(tab) {
                        this.tab = tab;
                        if (tab !== 'preview') { this.\$nextTick(() => this.cm && this.cm.refresh()); return; }
                        this.loading = true;
                        try {
                            this.doc = await \$callSchemaComponentMethod('renderPreview', { markdown: \$wire.get({$path}) ?? '', dark: document.documentElement.classList.contains('dark') });
                        } finally { this.loading = false; }
                    },
                }" x-on:cb-md-tab.window="if (\$event.detail.path === {$path}) show(\$event.detail.tab)">
                <div class="cb-md-resize" role="separator" aria-orientation="horizontal" aria-label="Resize the editor (arrow keys)" tabindex="0"
                    aria-valuemin="120" aria-valuemax="2000" aria-valuenow="{$height}" :aria-valuenow="height" :aria-valuetext="height + ' pixels'"
                    x-on:pointerdown.prevent="startResize(\$event)" x-on:keydown.up.prevent="nudge(-40)" x-on:keydown.down.prevent="nudge(40)">
                    <svg aria-hidden="true" viewBox="0 0 12 12" width="12" height="12"><path d="M11 4 4 11M11 8 8 11" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </div>
                <div x-show="tab === 'preview'" x-cloak class="cb-md-preview" :style="{ height: (height + 48) + 'px' }">
                    <div class="cb-md-preview-bar" aria-hidden="true"></div>
                    <p x-show="loading" class="cb-md-loading">Rendering preview…</p>
                    <iframe x-show="! loading" title="Preview" sandbox="" :srcdoc="doc" loading="lazy"></iframe>
                </div>
            </div>
            HTML;
    }
}
