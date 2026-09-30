<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\SupportingDocuments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A scan, a PDF, a screenshot of a statement: the file behind a supporting
 * document.
 *
 * Kept on the private `local` disk like an expense report's proofs, and served
 * only through the controller once the policy has spoken.
 *
 * @property int $id
 * @property int $supporting_document_id
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property string $sha256
 * @property-read SupportingDocument $supportingDocument
 */
class SupportingDocumentFile extends Model
{
    protected $fillable = [
        'path',
        'original_name',
        'mime_type',
        'size',
        'sha256',
    ];

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * @return BelongsTo<SupportingDocument, $this>
     */
    public function supportingDocument(): BelongsTo
    {
        return $this->belongsTo(SupportingDocument::class)->withTrashed();
    }
}
