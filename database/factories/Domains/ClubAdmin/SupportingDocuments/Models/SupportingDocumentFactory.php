<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\SupportingDocuments\Models;

use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\IncomeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;

/**
 * An expense document by default, with one PDF on the private disk.
 *
 * @extends Factory<SupportingDocument>
 */
class SupportingDocumentFactory extends Factory
{
    protected $model = SupportingDocument::class;

    public function configure(): static
    {
        return $this->afterCreating(function (SupportingDocument $document): void {
            if ($document->files()->doesntExist()) {
                $file = UploadedFile::fake()->create('facture.pdf', 20, 'application/pdf');

                $document->files()->create([
                    'path' => $file->store("supporting-documents/{$document->id}", 'local'),
                    'original_name' => 'facture.pdf',
                    'mime_type' => 'application/pdf',
                    'size' => (int) $file->getSize(),
                    'sha256' => (string) hash_file('sha256', $file->getRealPath()),
                ]);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => $this->faker->dateTimeBetween('-6 months')->format('Y-m-d'),
            'amount' => $this->faker->randomFloat(2, 5, 500),
            'expense_category' => ExpenseCategory::Other,
            'income_category' => null,
            'counterparty' => $this->faker->company(),
            'label' => $this->faker->sentence(3),
        ];
    }

    public function expense(ExpenseCategory $category = ExpenseCategory::Other): static
    {
        return $this->state(['expense_category' => $category, 'income_category' => null]);
    }

    public function income(IncomeCategory $category = IncomeCategory::Other): static
    {
        return $this->state(['expense_category' => null, 'income_category' => $category]);
    }
}
