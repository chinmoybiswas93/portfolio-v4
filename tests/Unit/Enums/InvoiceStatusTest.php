<?php

declare(strict_types=1);

use App\Enums\Currency;
use App\Enums\InvoiceStatus;

it('allows only valid status transitions', function (InvoiceStatus $from, InvoiceStatus $to, bool $allowed): void {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with(function (): array {
    $allowed = [
        'draft' => ['sent'], // a draft has no number: it is deleted, never cancelled
        'sent' => ['overdue', 'paid', 'cancelled'],
        'overdue' => ['paid', 'cancelled'],
        'paid' => [],
        'cancelled' => [],
    ];
    $cases = [];
    foreach (InvoiceStatus::cases() as $from) {
        foreach (InvoiceStatus::cases() as $to) {
            $cases["{$from->value} -> {$to->value}"] = [$from, $to, in_array($to->value, $allowed[$from->value], true)];
        }
    }

    return $cases;
});

it('locks every status except draft', function (): void {
    expect(InvoiceStatus::Draft->isLocked())->toBeFalse();
    foreach ([InvoiceStatus::Sent, InvoiceStatus::Overdue, InvoiceStatus::Paid, InvoiceStatus::Cancelled] as $status) {
        expect($status->isLocked())->toBeTrue();
    }
});

it('knows currency symbols and minor units', function (): void {
    expect(Currency::GBP->symbol())->toBe('£')
        ->and(Currency::EUR->symbol())->toBe('€')
        ->and(Currency::USD->symbol())->toBe('$')
        ->and(Currency::GBP->minorUnits())->toBe(2);
});
