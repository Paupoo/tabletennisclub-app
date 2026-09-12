<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Attestations\StoreAttestationImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Storage::fake('local');
});

function specimenUpload(string $name): UploadedFile
{
    return new UploadedFile(
        base_path('database/seeders/Data/attestation-specimens/' . $name . '.png'),
        $name . '.png',
        'image/png',
        test: true,
    );
}

it('takes in a seal that carries transparency', function (): void {
    $settings = app(StoreAttestationImage::class)(specimenUpload('specimen-seal'), StoreAttestationImage::SEAL);

    expect($settings->seal_path)->not->toBeNull()
        ->and(is_file($settings->seal_path))->toBeTrue();
});

it('refuses a seal on a solid background, which would hide the form underneath', function (): void {
    expect(fn () => app(StoreAttestationImage::class)(specimenUpload('opaque-seal'), StoreAttestationImage::SEAL))
        ->toThrow(ValidationException::class);
});

it('replaces the previous scan rather than piling them up', function (): void {
    $first = app(StoreAttestationImage::class)(specimenUpload('specimen-seal'), StoreAttestationImage::SEAL)->seal_path;
    $second = app(StoreAttestationImage::class)(specimenUpload('specimen-seal'), StoreAttestationImage::SEAL)->seal_path;

    expect($second)->not->toBe($first)
        ->and(is_file($first))->toBeFalse()
        ->and(is_file($second))->toBeTrue();
});

it('keeps the seal and the signature apart', function (): void {
    app(StoreAttestationImage::class)(specimenUpload('specimen-seal'), StoreAttestationImage::SEAL);
    $settings = app(StoreAttestationImage::class)(specimenUpload('specimen-signature'), StoreAttestationImage::SIGNATURE);

    expect($settings->seal_path)->not->toBe($settings->signature_path)
        ->and(is_file($settings->seal_path))->toBeTrue()
        ->and(is_file($settings->signature_path))->toBeTrue();
});

it('crops the empty canvas around the mark, so a millimetre means a millimetre', function (): void {
    // The seal that prompted this: 248 px of ink adrift in a 680 px image, so
    // a 30 mm setting drew an 11 mm stamp.
    $canvas = imagecreatetruecolor(680, 400);
    imagesavealpha($canvas, true);
    imagealphablending($canvas, false);
    imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
    imagefilledellipse($canvas, 340, 200, 248, 248, imagecolorallocate($canvas, 21, 74, 138));

    $path = sys_get_temp_dir() . '/wide-canvas-seal.png';
    imagepng($canvas, $path);

    $settings = app(StoreAttestationImage::class)(
        new UploadedFile($path, 'seal.png', 'image/png', test: true),
        StoreAttestationImage::SEAL,
    );

    [$width, $height] = getimagesize($settings->seal_path);

    // The ink plus a hairline of padding, not the original canvas.
    expect($width)->toBeLessThan(280)
        ->and($width)->toBeGreaterThan(240)
        ->and($height)->toBeLessThan(280);
});

it('leaves an image that is all ink alone', function (): void {
    $settings = app(StoreAttestationImage::class)(
        specimenUpload('specimen-signature'),
        StoreAttestationImage::SIGNATURE,
    );

    expect(is_file($settings->signature_path))->toBeTrue();
});
