<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * FAQ entries stored as JSON in a `faq` column (ADR-0010): posts and service pages show them and describe them
 * as an FAQPage.
 */
trait HasFaq
{
    /**
     * Entries with both a question and an answer.
     *
     * @return list<array{question: string, answer: string}>
     */
    public function faqItems(): array
    {
        $items = [];
        foreach ((array) $this->getAttribute('faq') as $row) {
            $question = trim((string) ($row['question'] ?? ''));
            $answer = trim((string) ($row['answer'] ?? ''));
            if ($question !== '' && $answer !== '') {
                $items[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $items;
    }
}
