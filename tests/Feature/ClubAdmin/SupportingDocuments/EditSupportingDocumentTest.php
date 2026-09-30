<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\DeleteSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\LinkSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\UpdateSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\IncomeCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

it('corrects a document, swapping its file for another', function (): void {
    $document = SupportingDocument::factory()->create();
    $old = $document->files()->sole();

    (new UpdateSupportingDocument)(
        document: $document,
        category: IncomeCategory::Sponsorship,
        date: Carbon::parse('2026-05-02'),
        amount: 500,
        counterparty: 'Brasserie du Blocry',
        label: 'Sponsoring maillots',
        newFiles: [UploadedFile::fake()->image('contrat.jpg')],
        removedFileIds: [$old->id],
    );

    $document->refresh();

    expect($document->income_category)->toBe(IncomeCategory::Sponsorship)
        ->and($document->expense_category)->toBeNull()
        ->and($document->amount)->toBe(500.0)
        ->and($document->files->pluck('original_name')->all())->toBe(['contrat.jpg']);
    Storage::disk('local')->assertMissing($old->path);
});

it('keeps at least one file on a document', function (): void {
    $document = SupportingDocument::factory()->create();

    (new UpdateSupportingDocument)(
        document: $document,
        category: ExpenseCategory::Other,
        date: Carbon::parse('2026-05-02'),
        amount: 10,
        counterparty: 'X',
        label: 'Y',
        removedFileIds: [$document->files()->sole()->id],
    );
})->throws(DomainException::class);

it('deletes a document linked to nothing, keeping it in the bin', function (): void {
    $document = SupportingDocument::factory()->create();

    (new DeleteSupportingDocument)($document);

    expect(SupportingDocument::find($document->id))->toBeNull()
        ->and(SupportingDocument::withTrashed()->find($document->id))->not->toBeNull();
});

it('refuses to delete a document that justifies a movement', function (): void {
    $document = SupportingDocument::factory()->create();
    (new LinkSupportingDocument)($document, Transaction::create(['date' => '2026-05-02', 'description' => 'X', 'amount' => -10]));

    (new DeleteSupportingDocument)($document);
})->throws(DomainException::class);
