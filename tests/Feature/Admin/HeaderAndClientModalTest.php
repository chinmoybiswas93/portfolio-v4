<?php

declare(strict_types=1);

use App\Filament\Resources\Clients\ClientModal;
use App\Filament\Resources\Clients\Pages\ManageClients;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\Category;
use App\Models\Client;
use App\Models\Post;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(adminWithMfa());
});

// ADR-0010: one client modal for the Clients list and the invoice's client field.
it('opens every client modal with the same heading and width', function (): void {
    $client = Client::factory()->create();

    $create = Livewire::test(ManageClients::class)->mountAction('create');
    $edit = Livewire::test(ManageClients::class)->mountAction(TestAction::make('edit')->table($client));
    $fromInvoice = Livewire::test(CreateInvoice::class)->mountAction(TestAction::make('createOption')->schemaComponent('client_id'));

    foreach ([$create, $edit, $fromInvoice] as $page) {
        expect($page->instance()->getMountedAction()?->getModalWidth())->toBe(ClientModal::WIDTH);
    }
    expect($create->instance()->getMountedAction()?->getModalHeading())->toBe('New client')
        ->and($fromInvoice->instance()->getMountedAction()?->getModalHeading())->toBe('New client')
        ->and($edit->instance()->getMountedAction()?->getModalHeading())->toBe('Edit client');
});

it('creates a client from the invoice with the shared form', function (): void {
    Livewire::test(CreateInvoice::class)
        ->mountAction(TestAction::make('createOption')->schemaComponent('client_id'))
        ->fillForm(['company_name' => 'Modal Ltd', 'email' => 'billing@modal.test', 'default_currency' => 'GBP'])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect(Client::query()->where('company_name', 'Modal Ltd')->exists())->toBeTrue();
});

// ADR-0010: a long title is cut to one line in CSS; the full title stays available as its tooltip.
it('gives a long edit-page title its full text as a tooltip', function (): void {
    $title = 'A very long post title that keeps going well past the width of the header column & more';
    $post = Post::factory()->for(Category::factory())->create(['title' => $title]);

    Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
        ->assertSeeHtml('<span title="Edit '.e($title).'">Edit '.e($title).'</span>');
});

// ADR-0010: each breadcrumb is capped in CSS and keeps its full text as a tooltip.
it('gives every breadcrumb its full text as a tooltip', function (): void {
    $title = 'WordPress White Screen After a Plugin Update: How I Fixed It in 2 Hours';
    $post = Post::factory()->for(Category::factory())->create(['title' => $title]);

    Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
        ->assertSeeHtml('<span title="'.e($title).'">'.e($title).'</span>')
        ->assertSeeHtml('<span title="Posts">Posts</span>')
        ->assertSeeHtml('<span title="Edit">Edit</span>');
});
