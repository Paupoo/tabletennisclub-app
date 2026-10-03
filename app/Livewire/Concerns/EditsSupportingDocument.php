<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\CreateSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\UpdateSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\IncomeCategory;
use App\Support\UploadLimits;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * The supporting document form, shared by the three screens that file one:
 * the documents list, the « Justifier » drawer of a bank line, and a cash
 * movement of the till.
 *
 * The component authorises before calling {@see saveDocumentForm()}: this
 * trait only validates and writes. The Blade side is
 * `<x-admin.treasury.supporting-document-form>`.
 */
trait EditsSupportingDocument
{
    /** Highest number of files on one document. */
    public const int MAX_DOCUMENT_FILES = 5;

    public string $documentAmount = '';

    public string $documentCategory = '';

    public string $documentCounterparty = '';

    public string $documentDate = '';

    /** @var array<int, mixed> */
    public array $documentFiles = [];

    public string $documentLabel = '';

    /** The document being corrected, null for a new one. */
    public ?int $editingDocumentId = null;

    /** @var list<int> */
    public array $removedDocumentFileIds = [];

    /**
     * The document being corrected, with the files it already carries.
     */
    public function editingDocument(): ?SupportingDocument
    {
        return $this->editingDocumentId === null ? null : SupportingDocument::with('files')->find($this->editingDocumentId);
    }

    public function removeExistingDocumentFile(int $fileId): void
    {
        $this->removedDocumentFileIds[] = $fileId;
    }

    public function removeNewDocumentFile(int $index): void
    {
        unset($this->documentFiles[$index]);
        $this->documentFiles = array_values($this->documentFiles);
    }

    protected function fillDocumentFormFrom(SupportingDocument $document): void
    {
        $this->resetDocumentForm();
        $this->editingDocumentId = $document->id;
        $this->documentCategory = $document->categoryKey();
        $this->documentDate = $document->date->toDateString();
        $this->documentAmount = number_format($document->amount, 2, '.', '');
        $this->documentCounterparty = $document->counterparty;
        $this->documentLabel = $document->label;
    }

    /**
     * What the movement already says: its date, its amount, who was on the
     * other side. The category, the label and the file are the treasurer's.
     */
    protected function prefillDocumentFormFrom(Transaction|CashRegisterEntry $movement): void
    {
        $this->resetDocumentForm();

        if ($movement instanceof Transaction) {
            $this->documentDate = $movement->date->toDateString();
            $this->documentAmount = number_format(abs((float) $movement->amount), 2, '.', '');
            $this->documentCounterparty = (string) $movement->counterparty_name;

            return;
        }

        $this->documentDate = ($movement->created_at ?? now())->toDateString();
        $this->documentAmount = number_format(abs($movement->amount) / 100, 2, '.', '');
        $this->documentLabel = $movement->reason === 'manual' ? '' : $movement->reason;
    }

    protected function resetDocumentForm(): void
    {
        $this->reset(['editingDocumentId', 'documentCategory', 'documentDate', 'documentAmount', 'documentCounterparty', 'documentLabel', 'documentFiles', 'removedDocumentFileIds']);
        $this->resetValidation();
    }

    /**
     * Validate and write the form: a new document, or the one being corrected.
     */
    protected function saveDocumentForm(): SupportingDocument
    {
        $this->documentAmount = str_replace([',', ' ', '€'], ['.', '', ''], $this->documentAmount);
        $editing = $this->editingDocument();

        $this->validate([
            'documentCategory' => ['required', Rule::in(array_column(SupportingDocument::categoryOptions(), 'id'))],
            'documentDate' => ['required', 'date'],
            'documentAmount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'documentCounterparty' => ['required', 'string', 'max:255'],
            'documentLabel' => ['required', 'string', 'max:255'],
            'documentFiles' => [$editing === null ? 'required' : 'nullable', 'array', 'max:' . self::MAX_DOCUMENT_FILES],
            'documentFiles.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', UploadLimits::documentRule()],
        ], [], [
            'documentCategory' => __('Category'),
            'documentDate' => __('Date of the document'),
            'documentAmount' => __('Amount'),
            'documentCounterparty' => __('Counterparty'),
            'documentLabel' => __('Label'),
            'documentFiles' => __('Files'),
            'documentFiles.*' => __('File'),
        ]);

        /** @var ExpenseCategory|IncomeCategory $category */
        $category = SupportingDocument::categoryFromKey($this->documentCategory);
        $date = Carbon::parse($this->documentDate);
        $amount = round((float) $this->documentAmount, 2);

        if ($editing instanceof SupportingDocument) {
            (new UpdateSupportingDocument)(
                document: $editing,
                category: $category,
                date: $date,
                amount: $amount,
                counterparty: $this->documentCounterparty,
                label: $this->documentLabel,
                newFiles: $this->documentFiles,
                removedFileIds: $this->removedDocumentFileIds,
            );

            return $editing->refresh();
        }

        /** @var User|null $author */
        $author = Auth::user();

        return (new CreateSupportingDocument)(
            category: $category,
            date: $date,
            amount: $amount,
            counterparty: $this->documentCounterparty,
            label: $this->documentLabel,
            files: $this->documentFiles,
            author: $author,
        );
    }
}
