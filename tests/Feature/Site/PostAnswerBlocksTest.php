<?php

declare(strict_types=1);

use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\Category;
use App\Models\Post;
use App\Settings\ProfileSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ADR-0010 slice 8c: key takeaways, FAQ (always visible, with FAQPage data), "Updated" date and the author box.

beforeEach(function (): void {
    config(['app.url' => 'https://example.com', 'cache.pages_enabled' => false]);
    $profile = app(ProfileSettings::class);
    $profile->name = 'Sam Example';
    $profile->title = 'WordPress developer';
    $profile->socials = [['network' => 'linkedin', 'url' => 'https://www.linkedin.com/in/example'], ['network' => 'x', 'url' => 'https://x.com/example']];
    $profile->save();
});

/** @return array<string, array<string, mixed>> the page's JSON-LD nodes by type */
function nodesOf(string $html): array
{
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

    return collect(json_decode($m[1] ?? '{}', true)['@graph'] ?? [])->keyBy('@type')->all();
}

it('shows key takeaways, the FAQ and the updated date, and describes the FAQ for search', function (): void {
    $post = Post::factory()->for(Category::factory())->create([
        'published_at' => '2026-11-01 09:00:00',
        'reviewed_at' => '2026-11-20',
        'takeaways' => ['Reproduce it like a customer', '   ', 'Log the payment step'],
        'faq' => [['question' => 'Why no error?', 'answer' => "A plugin <b>stops</b> it.\nTurn on logging."], ['question' => 'Incomplete', 'answer' => '']],
    ]);

    $html = (string) $this->get(route('posts.show', $post))->assertOk()
        ->assertSee('Updated <time datetime="2026-11-20">20 Nov 2026</time>', false)
        ->assertSeeInOrder(['Key takeaways', 'Reproduce it like a customer', 'Log the payment step', 'Frequently asked questions', 'Why no error?'])
        ->assertSee('A plugin &lt;b&gt;stops&lt;/b&gt; it.<br />', false)
        ->assertDontSee('Incomplete')
        ->getContent();

    $nodes = nodesOf($html);
    expect($nodes['FAQPage']['mainEntity'])->toBe([[
        '@type' => 'Question', 'name' => 'Why no error?',
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => "A plugin <b>stops</b> it.\nTurn on logging."],
    ]])->and($nodes['BlogPosting']['dateModified'])->toBe($post->reviewed_at?->toAtomString());
});

it('shows nothing extra for a post without them, and no "Updated" for a same-day review', function (): void {
    $post = Post::factory()->for(Category::factory())->create(['published_at' => '2026-11-01 09:00:00', 'reviewed_at' => '2026-11-01']);

    $html = (string) $this->get(route('posts.show', $post))->assertOk()
        ->assertDontSee('Key takeaways')->assertDontSee('Frequently asked questions')->assertDontSee('Updated <time', false)
        ->getContent();

    expect(nodesOf($html))->not->toHaveKey('FAQPage')
        ->and(nodesOf($html)['BlogPosting']['dateModified'])->toBe($post->published_at?->toAtomString());
});

it('introduces the author with role and profile links', function (): void {
    $post = Post::factory()->for(Category::factory())->create();

    $this->get(route('posts.show', $post))
        ->assertSee('Sam Example<span class="author-role"> · WordPress developer</span>', false)
        ->assertSee('<a href="https://www.linkedin.com/in/example" rel="me noopener" target="_blank">LinkedIn</a>', false)
        ->assertDontSee('href="https://x.com/example"', false);
});

it('edits takeaways, FAQ and the review date in the post editor', function (): void {
    $this->actingAs(adminWithMfa());
    $post = Post::factory()->for(Category::factory())->create();

    Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
        ->fillForm([
            'takeaways' => [['text' => 'First point'], ['text' => 'Second point']],
            'faq' => [['question' => 'Q one?', 'answer' => 'A one.']],
            'reviewed_at' => now()->toDateString(),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $post->refresh();
    expect($post->takeawayList())->toBe(['First point', 'Second point'])
        ->and(array_values((array) $post->takeaways))->toBe(['First point', 'Second point']) // stored as plain strings
        ->and($post->faqItems())->toBe([['question' => 'Q one?', 'answer' => 'A one.']]);
});
