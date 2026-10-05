<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Finance\Services;

use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\Shared\ValueObjects\FiscalYear;
use Carbon\CarbonImmutable;

/**
 * The financial years worth offering in a year picker: the running one, and
 * every past one some money moved in — a bank line, a cash movement or a
 * supporting document. A year with nothing in it is left out.
 *
 * Paid expense reports need no source of their own: « paid » is read off a
 * bank line, so their year is already a bank line's.
 */
final class FiscalYearsOnRecord
{
    /**
     * Newest first, the running year on top.
     *
     * @return list<array{id: int, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (FiscalYear $year): array => ['id' => $year->startYear(), 'name' => $year->label()],
            self::years(),
        );
    }

    /**
     * @return list<FiscalYear>
     */
    public static function years(): array
    {
        $current = FiscalYear::current();
        $oldest = collect([
            Transaction::query()->min('date'),
            CashRegisterEntry::query()->min('created_at'),
            SupportingDocument::query()->min('date'),
        ])->filter()->min();

        if ($oldest === null) {
            return [$current];
        }

        $years = [$current];

        for ($year = $current->previous(); $year->startYear() >= FiscalYear::for(CarbonImmutable::parse($oldest))->startYear(); $year = $year->previous()) {
            if (self::holdsMoney($year)) {
                $years[] = $year;
            }
        }

        return $years;
    }

    private static function holdsMoney(FiscalYear $year): bool
    {
        $from = $year->start()->toDateString();
        $to = $year->end()->toDateString();

        return Transaction::query()->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->exists()
            || CashRegisterEntry::query()->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to)->exists()
            || SupportingDocument::query()->datedIn($year)->exists();
    }
}
