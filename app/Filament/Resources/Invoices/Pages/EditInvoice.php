<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Pages;

use App\Actions\Invoicing\ChangeInvoiceStatus;
use App\Actions\Invoicing\DuplicateInvoice;
use App\Actions\Invoicing\EmailInvoice;
use App\Actions\Invoicing\GenerateInvoicePdf;
use App\Actions\Invoicing\IssueInvoice;
use App\Actions\Invoicing\RecalculateInvoice;
use App\Domain\Invoicing\InvalidInvoice;
use App\Enums\EmailStatus;
use App\Enums\InvoiceAction;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Header actions as in the approved canvas: Generate PDF, Download, Email to client, Mark as paid,
 * Issue invoice, and a More menu (Duplicate, Cancel, Delete draft).
 */
class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    public function getTitle(): string
    {
        return $this->invoice()->number ?? 'Draft invoice';
    }

    public function getSubheading(): string
    {
        return $this->invoice()->status->getLabel().' · '.Money::format($this->invoice()->total_minor, $this->invoice()->currency);
    }

    protected function beforeSave(): void
    {
        if ($this->invoice()->status->isLocked()) {
            Notification::make()->danger()->title('Issued invoices cannot be changed.')->send();
            $this->halt();
        }
    }

    protected function afterSave(): void
    {
        if (! $this->invoice()->status->isLocked()) {
            app(RecalculateInvoice::class)($this->invoice());
        }
    }

    protected function getHeaderActions(): array
    {
        $allows = fn (InvoiceAction $action): \Closure => fn (): bool => $this->invoice()->status->allows($action);

        return [
            $this->getSaveFormAction()->formId('form')->label('Save draft')->color('gray')->visible($allows(InvoiceAction::Edit)),
            Action::make('generatePdf')->label(fn (): string => $this->invoice()->pdf_path ? 'Regenerate PDF' : 'Generate PDF')
                ->icon(Heroicon::DocumentArrowDown)->color('gray')
                ->visible($allows(InvoiceAction::GeneratePdf))
                ->action(function (): void {
                    app(GenerateInvoicePdf::class)($this->invoice());
                    Notification::make()->success()->title('PDF generated')->send();
                }),
            Action::make('download')->label('Download')->icon(Heroicon::ArrowDownTray)->color('gray')
                ->visible(fn (): bool => $this->invoice()->status->allows(InvoiceAction::Download) && $this->invoice()->pdf_path !== null)
                ->action(fn (): StreamedResponse => Storage::disk(GenerateInvoicePdf::DISK)->download((string) $this->invoice()->pdf_path, $this->invoice()->number.'.pdf')),
            Action::make('email')->label('Email to client')->icon(Heroicon::Envelope)->color('gray')
                ->visible($allows(InvoiceAction::Email))
                ->modalHeading('Email invoice')->modalSubmitActionLabel('Send email')
                ->fillForm(fn (): array => [
                    'to' => $this->invoice()->client_snapshot['email'] ?? $this->invoice()->client->email,
                    'cc' => [],
                    'subject' => 'Invoice '.$this->invoice()->number.' from '.($this->invoice()->seller_snapshot['name'] ?? ''),
                    'body' => $this->defaultEmailBody(),
                ])
                ->schema([
                    TextInput::make('to')->label('To')->email()->required(),
                    TagsInput::make('cc')->label('CC')->nestedRecursiveRules(['email'])->placeholder('Add an email'),
                    TextInput::make('subject')->required()->maxLength(200),
                    Textarea::make('body')->rows(8)->required()->maxLength(5000),
                ])
                ->action(function (array $data): void {
                    $user = auth()->user();
                    $log = app(EmailInvoice::class)(
                        $this->invoice(),
                        (string) $data['to'],
                        array_values(array_filter((array) ($data['cc'] ?? []), 'is_string')),
                        (string) $data['subject'],
                        (string) $data['body'],
                        $user instanceof User ? $user : null,
                    );
                    $log->status === EmailStatus::Sent
                        ? Notification::make()->success()->title('Invoice emailed to '.$log->to_email)->send()
                        : Notification::make()->danger()->title('Email not sent')->body((string) $log->error)->persistent()->send();
                    $this->refreshFormData(['emails']);
                }),
            Action::make('markPaid')->label('Mark as paid')->icon(Heroicon::CheckCircle)->color('gray')
                ->visible($allows(InvoiceAction::MarkPaid))
                ->requiresConfirmation()
                ->action(fn () => $this->changeStatus(InvoiceStatus::Paid, 'Marked as paid')),
            Action::make('issue')->label('Issue invoice')
                ->visible($allows(InvoiceAction::Issue))
                ->requiresConfirmation()
                ->modalDescription('This assigns the next invoice number, locks the lines and amounts, and creates the PDF.')
                ->action(function (): void {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    try {
                        app(IssueInvoice::class)($this->invoice()->refresh());
                        Notification::make()->success()->title('Issued as '.$this->invoice()->refresh()->number)->send();
                        $this->redirect(InvoiceResource::getUrl('edit', ['record' => $this->invoice()]));
                    } catch (InvalidInvoice $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
            ActionGroup::make([
                Action::make('duplicate')->label('Duplicate as new draft')->icon(Heroicon::DocumentDuplicate)
                    ->action(function (): void {
                        $copy = app(DuplicateInvoice::class)($this->invoice()->load('items'));
                        $this->redirect(InvoiceResource::getUrl('edit', ['record' => $copy]));
                    }),
                Action::make('cancel')->label('Cancel invoice')->icon(Heroicon::XCircle)->color('danger')
                    ->visible($allows(InvoiceAction::Cancel))
                    ->requiresConfirmation()
                    ->action(fn () => $this->changeStatus(InvoiceStatus::Cancelled, 'Invoice cancelled')),
                DeleteAction::make()->label('Delete draft')->visible($allows(InvoiceAction::Delete)),
            ])->label('More')->icon(Heroicon::EllipsisVertical)->color('gray'),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    private function changeStatus(InvoiceStatus $to, string $message): void
    {
        try {
            app(ChangeInvoiceStatus::class)($this->invoice(), $to);
            Notification::make()->success()->title($message)->send();
        } catch (InvalidInvoice $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }

    private function defaultEmailBody(): string
    {
        $invoice = $this->invoice();
        $name = $invoice->client_snapshot['contact_name'] ?? null;

        return ($name ? "Hi {$name},\n\n" : "Hello,\n\n")
            ."Please find attached invoice {$invoice->number} for ".Money::format($invoice->total_minor, $invoice->currency)
            .', due on '.$invoice->due_date?->format('j F Y').".\n\nThank you,\n".($invoice->seller_snapshot['name'] ?? '');
    }

    private function invoice(): Invoice
    {
        /** @var Invoice $record */
        $record = $this->getRecord();

        return $record;
    }
}
