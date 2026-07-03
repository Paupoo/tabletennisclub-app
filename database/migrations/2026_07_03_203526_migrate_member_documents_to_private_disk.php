<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Member documents (medical certificates, parental consents) used to live on
 * the public disk, reachable by anyone at /storage/documents/{id}/... — health
 * and minor data. This data migration moves the files to the private local
 * disk and rewrites the users path columns from "/storage/{relative}" to the
 * bare "{relative}" storage path served by the authenticated download route.
 */
return new class extends Migration
{
    private const array COLUMNS = ['medical_certificate_path', 'parental_consent_path'];

    public function down(): void
    {
        foreach ($this->rowsWithDocuments() as $row) {
            $updates = [];

            foreach (self::COLUMNS as $column) {
                $path = $row->{$column};

                if ($path === null || str_starts_with($path, '/storage/')) {
                    continue;
                }

                if (Storage::disk('local')->exists($path)) {
                    Storage::disk('public')->put($path, Storage::disk('local')->get($path));
                    Storage::disk('local')->delete($path);
                }

                $updates[$column] = "/storage/{$path}";
            }

            if ($updates !== []) {
                DB::table('users')->where('id', $row->id)->update($updates);
            }
        }
    }

    public function up(): void
    {
        foreach ($this->rowsWithDocuments() as $row) {
            $updates = [];

            foreach (self::COLUMNS as $column) {
                $path = $row->{$column};

                if ($path === null || ! str_starts_with($path, '/storage/')) {
                    continue;
                }

                $relative = substr($path, strlen('/storage/'));

                if (Storage::disk('public')->exists($relative)) {
                    Storage::disk('local')->put($relative, Storage::disk('public')->get($relative));
                    Storage::disk('public')->delete($relative);
                }

                $updates[$column] = $relative;
            }

            if ($updates !== []) {
                DB::table('users')->where('id', $row->id)->update($updates);
            }
        }
    }

    /** @return Collection<int, object> */
    private function rowsWithDocuments(): Collection
    {
        return DB::table('users')
            ->select(['id', ...self::COLUMNS])
            ->where(function ($query): void {
                $query->whereNotNull('medical_certificate_path')
                    ->orWhereNotNull('parental_consent_path');
            })
            ->get();
    }
};
