<?php

declare(strict_types=1);

use App\Domains\Shared\Support\IbanNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalize('users', 'iban');
        $this->normalize('guardians', 'iban');
        $this->normalize('clubs', 'bank_account');
    }

    private function normalize(string $table, string $column): void
    {
        DB::table($table)
            ->whereNotNull($column)
            ->orderBy('id')
            ->each(function (object $row) use ($table, $column): void {
                $normalized = IbanNormalizer::normalize($row->{$column});

                if ($normalized !== $row->{$column}) {
                    DB::table($table)->where('id', $row->id)->update([$column => $normalized]);
                }
            });
    }
};
