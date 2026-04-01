<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
    private const DISK = 'public';

    /** @var array<string, array<int, string>> */
    private static array $columnsCache = [];

    /**
     * Handle image data coming from a validated request.
     *
     * Call this from controllers *before* passing data to InventoryCrudService.
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
        $file = $data['image'] ?? null;
        $url  = $data['image_url'] ?? null;

        // Remove the raw UploadedFile key so it never reaches Eloquent's fill()
        unset($data['image']);

        if ($file instanceof UploadedFile) {
            // Uploaded file takes priority
            $this->deleteOldImage($existing);

            $path = $this->storeFile($file, $folder);

            $data['image_path'] = $path;
            $data['image_url']  = null;        // clear URL — upload wins
        } elseif (is_string($url) && $url !== '') {
            // External URL provided — clear any old uploaded file
            $this->deleteOldImage($existing);

            $data['image_path'] = null;
            $data['image_url']  = $url;
        } elseif (array_key_exists('image_url', $data) && $url === null) {
            // Explicitly cleared — remove both
            $this->deleteOldImage($existing);
            $data['image_path'] = null;
        }
        // else: neither provided → leave current values untouched
    }

    /**
     * Store an uploaded file and return its disk-relative path.
     */
    public function storeFile(UploadedFile $file, string $folder): string
    {
        $name = Str::ulid().'.'.$file->getClientOriginalExtension();

        return $file->storeAs("images/{$folder}", $name, self::DISK);
    }

    /**
     * Delete the previously uploaded image (if any) from disk.
     */
    public function deleteOldImage(?Model $existing): void
    {
        if (! $existing) {
            return;
        }

        $oldPath = $existing->getAttribute('image_path');

        if ($oldPath && Storage::disk(self::DISK)->exists($oldPath)) {
            Storage::disk(self::DISK)->delete($oldPath);
        }
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
        $model = $existing ?? (is_string($modelOrClass) ? new $modelOrClass() : $modelOrClass);
        $columns = $this->getTableColumns($model);

        $supportsImagePath = in_array('image_path', $columns, true);
        $supportsImageUrl = in_array('image_url', $columns, true);

        if (! $supportsImagePath) {
            unset($data['image']);
        }

        if (method_exists($model, 'imageFolder') && ($supportsImagePath || $supportsImageUrl)) {
            $this->handleImageData($data, $model::imageFolder(), $existing);
        }

        if (! $supportsImagePath) {
            unset($data['image_path']);
        }

        if (! $supportsImageUrl) {
            unset($data['image_url']);
        }

        return array_intersect_key($data, array_flip($columns));
    }

    /** @return array<int, string> */
    private function getTableColumns(Model $model): array
    {
        $table = $model->getTable();

        if (! isset(self::$columnsCache[$table])) {
            self::$columnsCache[$table] = Schema::getColumnListing($table);
        }

        return self::$columnsCache[$table];
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
