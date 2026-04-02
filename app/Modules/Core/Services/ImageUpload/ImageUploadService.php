<?php

declare(strict_types=1);

namespace App\Modules\Core\Services\ImageUpload;

use App\Modules\Core\Services\ImageUpload\ImagePayloadPreparerService;
use App\Modules\Core\Services\ImageUpload\ImageStorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * ImageUploadService — central service for all image upload operations.
 *
 * Responsibilities:
 *  • Store uploaded files on the "public" disk under entity-specific folders
 *  • Delete old images when replaced or when a record is deleted
 *  • Resolve a single public URL from either `image_path` or `image_url`
 *
 * Folder structure:  storage/app/public/images/{entity}/
 *   e.g. images/plant-species/abc123.webp
 */
class ImageUploadService
{
    public function __construct(
        private readonly ImagePayloadPreparerService $payloadPreparer,
        private readonly ImageStorageService $storage,
    ) {
    }

    /**
     * Handle image data coming from a validated request.
     *
    * Call this from controllers *before* passing data to CrudService.
     * It mutates the `$data` array in-place:
     *   • If a file was uploaded   → stores it and sets `image_path`
     *   • If an external URL given → keeps `image_url`, clears `image_path`
     *   • If neither               → leaves both untouched
     *   • Always removes the raw `image` key (the UploadedFile)
     *
     * @param  array<string, mixed>  $data       Validated request data (by reference)
     * @param  string                $folder     Sub-folder under images/ (e.g. "equipment")
     * @param  Model|null            $existing   The existing model instance (on updates)
     */
    public function handleImageData(array &$data, string $folder, ?Model $existing = null): void
    {
        $this->payloadPreparer->handleImageData($data, $folder, $existing);
    }

    /**
     * Store an uploaded file and return its disk-relative path.
     */
    public function storeFile(UploadedFile $file, string $folder): string
    {
        return $this->storage->storeFile($file, $folder);
    }

    /**
     * Delete the previously uploaded image (if any) from disk.
     */
    public function deleteOldImage(?Model $existing): void
    {
        $this->storage->deleteModelImagePath($existing);
    }

    /**
     * Delete an image for a model that is being destroyed.
     */
    public function deleteImageForModel(Model $model): void
    {
        $this->deleteOldImage($model);
    }

    /**
     * Prepare a validated payload for persistence against a given model.
     *
     * - Normalizes image keys (`image`, `image_path`, `image_url`) based on table support.
     * - Handles upload/url replacement logic when supported.
     * - Filters unknown keys to avoid SQL column errors.
     *
     * @param array<string, mixed> $data
     * @param class-string<Model>|Model $modelOrClass
     * @return array<string, mixed>
     */
    public function prepareDataForPersistence(array $data, string|Model $modelOrClass, ?Model $existing = null): array
    {
        return $this->payloadPreparer->prepareDataForPersistence($data, $modelOrClass, $existing);
    }

    /**
     * Resolve the single public-facing image URL.
     *
     * Priority: uploaded file (image_path) > external URL (image_url) > null
     */
    public static function resolveImageUrl(?string $imagePath, ?string $imageUrl): ?string
    {
        if ($imagePath) {
            return Storage::url($imagePath);
        }

        return $imageUrl;
    }
}
