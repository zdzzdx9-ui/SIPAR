<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Exceptions\RuntimeException as ImageRuntimeException;
use Intervention\Image\ImageManager;
use RuntimeException;

class PhotoCompressor
{
    private const MAX_PIXELS = 12000000;

    private const MAX_DIMENSION = 1920;

    public function store(UploadedFile $photo, string $directory, string $field = 'foto_bukti'): string
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('The GD extension is required to process uploaded photos.');
        }

        $imageInfo = @getimagesize($photo->getRealPath());
        if ($imageInfo === false || $imageInfo[0] * $imageInfo[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages([
                $field => 'Foto tidak valid atau resolusinya terlalu besar. Gunakan gambar maksimal 12 megapiksel.',
            ]);
        }

        $extension = match ($imageInfo['mime'] ?? null) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw ValidationException::withMessages([
                $field => 'Format foto harus JPG, PNG, atau WEBP.',
            ]),
        };

        try {
            $requiresResize = max($imageInfo[0], $imageInfo[1]) > self::MAX_DIMENSION;
            $image = ImageManager::gd()
                ->read($photo->getRealPath())
                ->orient()
                ->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);

            $encoded = match ($extension) {
                'jpg' => $image->toJpeg(quality: 82, progressive: true, strip: true),
                'png' => $image->toPng(),
                'webp' => $image->toWebp(quality: 82, strip: true),
            };
        } catch (ImageRuntimeException $exception) {
            throw ValidationException::withMessages([
                $field => 'Foto tidak dapat diproses. Silakan pilih file gambar yang valid.',
            ]);
        }

        $originalSize = $photo->getSize();
        $contents = $requiresResize || $encoded->size() < $originalSize
            ? $encoded->toString()
            : file_get_contents($photo->getRealPath());
        $path = trim($directory, '/').'/'.Str::uuid().'.'.$extension;

        if ($contents === false || ! Storage::disk('public')->put($path, $contents)) {
            throw new RuntimeException('Foto tidak dapat disimpan ke penyimpanan publik.');
        }

        return $path;
    }
}
