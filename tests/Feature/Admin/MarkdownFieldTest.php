<?php

declare(strict_types=1);

use App\Filament\Forms\MarkdownField;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ADR-0010: every Markdown field has Write / Preview tabs, a resize handle, and a server-rendered preview.

beforeEach(function (): void {
    $this->actingAs(adminWithMfa());
    $this->post = Post::factory()->for(Category::factory())->create();
});

it('draws the tabs, the resize handle and the preview frame under the editor, above the helper text', function (): void {
    $html = Livewire::test(EditPost::class, ['record' => $this->post->getRouteKey()])->html();

    expect($html)->toContain('aria-label="Editor mode"')
        ->and($html)->toContain('class="cb-md-resize"')
        ->and($html)->toContain('sandbox=""')
        ->and(strpos($html, 'class="cb-md-resize"'))->toBeLessThan(strpos($html, 'Code blocks with a language'));
});

it('renders the preview through the exposed component method with the site renderer', function (): void {
    $page = Livewire::test(EditPost::class, ['record' => $this->post->getRouteKey()]);
    $field = collect($page->instance()->form->getFlatComponents())
        ->first(fn (mixed $x): bool => $x instanceof MarkdownField && $x->getStatePath() === 'data.body_md');

    $doc = $page->instance()->callSchemaComponentMethod((string) $field?->getKey(), 'renderPreview', ['markdown' => "## Hi\n\n<script>alert(1)</script>", 'dark' => false]);

    expect($doc)->toContain('data-theme="light"')->toContain('<h2>Hi</h2>')
        ->toContain('&lt;script&gt;')->not->toContain('<script>alert');
});

it('shows a placeholder for an empty preview and caps very long input', function (): void {
    $field = MarkdownField::make('body_md');

    expect($field->renderPreview(''))->toContain('Nothing to preview yet.')
        ->and(strlen($field->renderPreview(str_repeat('a', MarkdownField::MAX_PREVIEW_LENGTH + 5000))))
        ->toBeLessThan(MarkdownField::MAX_PREVIEW_LENGTH + 2000);
});

it('uses the field on every Markdown input', function (): void {
    $editors = collect(['Posts/Schemas/PostForm', 'Projects/Schemas/ProjectForm', 'Experiences/Schemas/ExperienceForm', 'Services/Schemas/ServiceForm'])
        ->map(fn (string $f): string => (string) file_get_contents(app_path("Filament/Resources/{$f}.php")))
        ->push((string) file_get_contents(app_path('Filament/Pages/ManageProfile.php')));

    expect($editors->filter(fn (string $src): bool => str_contains($src, 'MarkdownEditor::make')))->toBeEmpty()
        ->and($editors->filter(fn (string $src): bool => str_contains($src, 'MarkdownField::make')))->toHaveCount(5);
});
