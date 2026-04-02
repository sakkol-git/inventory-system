<?php

declare(strict_types=1);

namespace App\Modules\Core\Services\ImageUpload;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageStorageService
{
    private const DISK = 'public';

    public function storeFile(UploadedFile $file, string $folder): string
    {
        $name = Str::ulid().'.'.$file->getClientOriginalExtension();

        return $file->storeAs("images/{$folder}", $name, self::DISK);
    }

    public function deleteModelImagePath(?Model $existing): void
    {
        if (! $existing) {
            return;
        }

        $this->deletePath($existing->getAttribute('image_path'));
    }

    public function deletePath(?string $path): void
    {
        if (! $path) {
            return;
        }

        $disk = Storage::disk(self::DISK);

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    public function resolveUploadedPathUrl(?string $path): ?string
    {
        return $path ? Storage::url($path) : null;
    }
}
