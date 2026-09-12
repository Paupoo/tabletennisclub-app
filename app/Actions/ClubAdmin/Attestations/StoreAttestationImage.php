<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Attestations;

use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\AttestationSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Takes in the club seal or the secretary's signature.
 *
 * A PNG with no alpha channel is refused outright. A seal scanned on a white
 * background looks fine on screen and then lands on the insurer's form as an
 * opaque white square covering the label underneath — the kind of defect
 * nobody notices until a mutual insurer sends the document back.
 *
 * Stored on the private disk: these two images are, between them, the club's
 * ability to certify anything.
 */
final readonly class StoreAttestationImage
{
    public const string SEAL = 'seal';

    public const string SIGNATURE = 'signature';

    public function __invoke(UploadedFile $file, string $kind): AttestationSetting
    {
        $this->refuseWithoutTransparency($file);

        $settings = AttestationSetting::current();
        $column = $kind . '_path';
        $previous = $settings->{$column};

        // A fresh name each time rather than a fixed one: browsers and the
        // PDF renderer alike cache by path, and a replaced seal that kept its
        // name would go on printing the old one.
        $relative = 'attestation-marks/' . $kind . '-' . Str::ulid() . '.png';
        Storage::disk('local')->put($relative, (string) file_get_contents($file->getRealPath()));

        $settings->update([$column => Storage::disk('local')->path($relative)]);

        // Replaced, not accumulated: the old scan is of no use to anybody and
        // it is still a usable club seal.
        if ($previous !== null && is_file($previous)) {
            @unlink($previous);
        }

        return $settings->refresh();
    }

    /**
     * @throws ValidationException
     */
    private function refuseWithoutTransparency(UploadedFile $file): void
    {
        $info = @getimagesize($file->getRealPath());

        if ($info === false || $info[2] !== IMAGETYPE_PNG) {
            throw ValidationException::withMessages([
                'upload' => __('The seal and the signature must be PNG files.'),
            ]);
        }

        // Colour type 4 (greyscale + alpha) and 6 (truecolour + alpha) carry a
        // channel; type 3 (palette) can carry a tRNS chunk instead.
        $header = (string) file_get_contents($file->getRealPath(), false, null, 0, 33);
        $colourType = ord(($header[25] ?? "\0")[0]);
        $hasPalette = $colourType === 3 && str_contains((string) file_get_contents($file->getRealPath()), 'tRNS');

        if (! in_array($colourType, [4, 6], true) && ! $hasPalette) {
            throw ValidationException::withMessages([
                'upload' => __('This image has no transparency. A seal on a solid background would hide the form underneath it — scan it again with a transparent background.'),
            ]);
        }
    }
}
