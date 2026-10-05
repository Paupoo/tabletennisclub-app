<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\SupportingDocuments\Actions\CreateSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\IncomeCategory;
use App\Domains\Shared\Enums\SupportingDocumentState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

it('files an invoice with its scan kept private, waiting to be paid', function (): void {
    $treasurer = User::factory()->create();

    $document = (new CreateSupportingDocument)(
        category: ExpenseCategory::Hall,
        date: Carbon::parse('2026-09-12'),
        amount: 1250.4,
        counterparty: 'Complexe sportif de Blocry',
        label: 'Location salle — 3e trimestre',
        files: [UploadedFile::fake()->create('facture-blocry.pdf', 80, 'application/pdf')],
        author: $treasurer,
    );

    expect($document->expense_category)->toBe(ExpenseCategory::Hall)
        ->and($document->income_category)->toBeNull()
        ->and($document->isExpense())->toBeTrue()
        ->and($document->amount)->toBe(1250.4)
        ->and($document->date->toDateString())->toBe('2026-09-12')
        ->and($document->created_by_id)->toBe($treasurer->id)
        ->and($document->state())->toBe(SupportingDocumentState::ToSettle)
        ->and($document->reference())->toBe(sprintf('P-2026-%04d', $document->id))
        ->and($document->files)->toHaveCount(1);

    Storage::disk('local')->assertExists($document->files->first()->path);
});

it('numbers a document after the financial year it falls in, both halves of a straddling one', function (string $date): void {
    Club::factory()->ownClub()->create(['fiscal_year_start_month' => 7]);
    Club::forgetOwnClub();

    $document = SupportingDocument::factory()->create(['date' => $date]);

    expect($document->reference())->toBe(sprintf('P-2627-%04d', $document->id));
})->with(['2026-07-01', '2027-03-10', '2027-06-30']);

it('files a subsidy letter as an income', function (): void {
    $document = (new CreateSupportingDocument)(
        category: IncomeCategory::Subsidies,
        date: Carbon::parse('2026-03-01'),
        amount: 800,
        counterparty: 'Ville d\'Ottignies-LLN',
        label: 'Subside sportif 2026',
        files: [UploadedFile::fake()->image('courrier.jpg')],
    );

    expect($document->isIncome())->toBeTrue()
        ->and($document->income_category)->toBe(IncomeCategory::Subsidies);
});

it('refuses a document without any file', function (): void {
    (new CreateSupportingDocument)(
        category: ExpenseCategory::Operations,
        date: Carbon::parse('2026-03-01'),
        amount: 12,
        counterparty: 'Banque',
        label: 'Frais de tenue de compte',
        files: [],
    );
})->throws(DomainException::class);

it('refuses the incomes only the website feeds', function (IncomeCategory $category): void {
    (new CreateSupportingDocument)(
        category: $category,
        date: Carbon::parse('2026-03-01'),
        amount: 120,
        counterparty: 'Un membre',
        label: 'Cotisation',
        files: [UploadedFile::fake()->image('preuve.jpg')],
    );
})->with([IncomeCategory::MembershipFees, IncomeCategory::Trainings])->throws(DomainException::class);
