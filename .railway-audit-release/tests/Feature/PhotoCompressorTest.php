<?php

namespace Tests\Feature;

use App\Services\PhotoCompressor;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhotoCompressorTest extends TestCase
{
    public function test_large_jpeg_is_resized_and_compressed_before_storage(): void
    {
        Storage::fake('public');
        $photo = $this->fakeImage('condition.jpg', 2400, 1600);
        $originalSize = filesize($photo->getRealPath());

        $path = app(PhotoCompressor::class)->store($photo, 'bukti/pengembalian');
        $storedPath = Storage::disk('public')->path($path);
        [$width, $height] = getimagesize($storedPath);

        $this->assertSame('jpg', pathinfo($path, PATHINFO_EXTENSION));
        $this->assertLessThanOrEqual(1920, max($width, $height));
        $this->assertLessThan($originalSize, filesize($storedPath));
        Storage::disk('public')->assertExists($path);
    }

    public function test_invalid_image_is_reported_as_a_validation_error(): void
    {
        Storage::fake('public');
        $photo = File::createWithContent('invalid.jpg', 'not an image');

        try {
            app(PhotoCompressor::class)->store($photo, 'bukti/pengembalian');
            $this->fail('Invalid image should not be stored.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('foto_bukti', $exception->errors());
        }

        $this->assertSame([], Storage::disk('public')->allFiles('bukti/pengembalian'));
    }
}
