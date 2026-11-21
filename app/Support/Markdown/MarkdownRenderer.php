<?php

declare(strict_types=1);

namespace App\Support\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Tempest\Highlight\CommonMark\HighlightExtension;

/**
 * Renders owner-authored Markdown to HTML once, on save (ADR-0001).
 * Raw HTML is escaped and unsafe link schemes (javascript:, data: …) are removed.
 */
final class MarkdownRenderer
{
    private ?MarkdownConverter $converter = null;

    public function render(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        return trim($this->converter()->convert($markdown)->getContent());
    }

    private function converter(): MarkdownConverter
    {
        if ($this->converter === null) {
            $environment = new Environment([
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 50,
            ]);
            $environment->addExtension(new CommonMarkCoreExtension);
            $environment->addExtension(new GithubFlavoredMarkdownExtension);
            $environment->addExtension(new HighlightExtension);

            $this->converter = new MarkdownConverter($environment);
        }

        return $this->converter;
    }
}
