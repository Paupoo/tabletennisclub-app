<?php

declare(strict_types=1);

namespace App\Http\Controllers\SupportingDocuments;

use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocumentFile;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the file behind a supporting document to whoever may read it.
 *
 * Same rules as an expense report's proofs: private disk, inline by default so
 * the drawer can show it, `?download=1` for the original, and never executed.
 */
class SupportingDocumentFileController extends Controller
{
    public function show(Request $request, SupportingDocumentFile $file): StreamedResponse
    {
        $this->authorize('view', $file->supportingDocument);

        abort_unless(Storage::disk('local')->exists($file->path), 404);

        if ($request->boolean('download')) {
            return Storage::disk('local')->download($file->path, $file->original_name);
        }

        return Storage::disk('local')->response($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
