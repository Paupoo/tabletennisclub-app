<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\SupportingDocuments\Actions;

use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocumentFile;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\IncomeCategory;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class UpdateSupportingDocument
{
    /**
     * Correct a document — always allowed, linked or not, and written to the
     * audit log. No financial year is locked yet.
     *
     * @param  array<int, UploadedFile>  $newFiles
     * @param  array<int, int>  $removedFileIds
     *
     * @throws DomainException when no file would be left, or under an income only the website feeds
     */
    public function __invoke(
        SupportingDocument $document,
        ExpenseCategory|IncomeCategory $category,
        CarbonInterface $date,
        float $amount,
        string $counterparty,
        string $label,
        array $newFiles = [],
        array $removedFileIds = [],
    ): void {
        SupportingDocumentRules::assertValid($category, $amount);

        $removed = $document->files()->whereKey($removedFileIds)->get();

        if ($document->files()->count() - $removed->count() + count($newFiles) < 1) {
            throw new DomainException(__('A supporting document needs at least one file.'));
        }

        DB::transaction(function () use ($document, $category, $date, $amount, $counterparty, $label, $removed): void {
            $document->update([
                'date' => $date->toDateString(),
                'amount' => $amount,
                'expense_category' => $category instanceof ExpenseCategory ? $category : null,
                'income_category' => $category instanceof IncomeCategory ? $category : null,
                'counterparty' => $counterparty,
                'label' => $label,
            ]);

            $removed->each(fn (SupportingDocumentFile $file): ?bool => $file->delete());
        });

        Storage::disk('local')->delete($removed->pluck('path')->all());

        (new StoreSupportingDocumentFiles)($document, $newFiles);
    }
}
