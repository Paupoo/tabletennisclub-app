<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\SupportingDocuments\Actions;

use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use Illuminate\Http\UploadedFile;

final class StoreSupportingDocumentFiles
{
    /**
     * Put the files on the private disk, one folder per document, fingerprinted
     * like an expense report's proofs.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function __invoke(SupportingDocument $document, array $files): void
    {
        foreach ($files as $file) {
            $document->files()->create([
                'path' => $file->store("supporting-documents/{$document->id}", 'local'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
                'sha256' => (string) hash_file('sha256', $file->getRealPath()),
            ]);
        }
    }
}
