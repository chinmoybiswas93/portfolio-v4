<?php

declare(strict_types=1);

use App\Support\Markdown\MarkdownRenderer;
use App\Support\Markdown\ReadingTime;

beforeEach(fn () => $this->md = new MarkdownRenderer);

it('renders markdown to html', function (): void {
    expect($this->md->render("## Title\n\nSome **bold** text."))
        ->toContain('<h2>Title</h2>')
        ->toContain('<strong>bold</strong>');
});

it('escapes raw html', function (): void {
    expect($this->md->render('<script>alert(1)</script>'))
        ->not->toContain('<script>')
        ->toContain('&lt;script&gt;');
});

it('removes unsafe link schemes', function (): void {
    expect($this->md->render('[x](javascript:alert(1))'))->not->toContain('javascript:');
});

it('highlights fenced php code on the server', function (): void {
    $html = $this->md->render("```php\n<?php echo 'hi';\n```");

    expect($html)->toContain('<pre')->toContain('hl-');
});

it('supports github flavoured tables', function (): void {
    expect($this->md->render("| a | b |\n|---|---|\n| 1 | 2 |"))->toContain('<table>');
});

it('returns an empty string for empty input', function (?string $input): void {
    expect($this->md->render($input))->toBe('');
})->with([null, '', "  \n "]);

it('estimates reading time with a one-minute floor', function (): void {
    expect(ReadingTime::minutes(''))->toBe(1)
        ->and(ReadingTime::minutes(str_repeat('word ', 220)))->toBe(1)
        ->and(ReadingTime::minutes(str_repeat('word ', 221)))->toBe(2)
        ->and(ReadingTime::minutes(str_repeat('word ', 1100)))->toBe(5);
});
