<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\SupportingDocuments\Actions;

use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\IncomeCategory;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class CreateSupportingDocument
{
    /**
     * File a supporting document: the invoice, the ticket, the letter, with at
     * least one file behind it.
     *
     * It starts to settle — a debt of the club when it is an expense, money
     * owed to the club when it is an income — until it is linked to the bank
     * line or the cash movement that paid it.
     *
     * @param  array<int, UploadedFile>  $files
     *
     * @throws DomainException without a file, or under an income only the website feeds
     */
    public function __invoke(
        ExpenseCategory|IncomeCategory $category,
        CarbonInterface $date,
        float $amount,
        string $counterparty,
        string $label,
        array $files,
        ?User $author = null,
    ): SupportingDocument {
        SupportingDocumentRules::assertValid($category, $amount);

        if ($files === []) {
            throw new DomainException(__('A supporting document needs at least one file.'));
        }

        $document = DB::transaction(fn (): SupportingDocument => SupportingDocument::create([
            'date' => $date->toDateString(),
            'amount' => $amount,
            'expense_category' => $category instanceof ExpenseCategory ? $category : null,
            'income_category' => $category instanceof IncomeCategory ? $category : null,
            'counterparty' => $counterparty,
            'label' => $label,
            'created_by_id' => $author?->id,
        ]));

        (new StoreSupportingDocumentFiles)($document, $files);

        return $document->load('files');
    }
}
