<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class BrandingFileStore
{
    public function store(UploadedFile $file, string $directory): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        Storage::disk('documents')->makeDirectory($directory);

        return $file->storeAs($directory, (string) Str::uuid().'.'.$extension, 'documents');
    }

    public function replace(?string $currentPath, UploadedFile $file, string $directory): string
    {
        $stored = $this->store($file, $directory);
        $this->delete($currentPath);

        return $stored;
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '' && ! str_contains($path, '..')) {
            Storage::disk('documents')->delete($path);
        }
    }

    public function dataUri(?string $path): ?string
    {
        if ($path === null || $path === '' || str_contains($path, '..') || ! Storage::disk('documents')->exists($path)) {
            return null;
        }

        $contents = Storage::disk('documents')->get($path);
        $mime = Storage::disk('documents')->mimeType($path) ?: 'application/octet-stream';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    public function response(?string $path): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if ($path === null || $path === '' || str_contains($path, '..') || ! Storage::disk('documents')->exists($path)) {
            abort(404, 'The requested resource was not found.');
        }

        return Storage::disk('documents')->response($path);
    }
}
