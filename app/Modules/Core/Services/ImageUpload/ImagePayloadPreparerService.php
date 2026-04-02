<?php

declare(strict_types=1);

namespace App\Modules\Core\Services\ImageUpload;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ImagePayloadPreparerService
{
    public function __construct(
        private readonly ImageSchemaService $schema,
        private readonly ImageStorageService $storage,
    ) {
    }

    /** @param  array<string, mixed>  $data */
    public function handleImageData(array &$data, string $folder, ?Model $existing = null): void
    {
        $file = $data['image'] ?? null;
        $url = $data['image_url'] ?? null;

        unset($data['image']);

        if ($file instanceof UploadedFile) {
            $this->storage->deleteModelImagePath($existing);
            $data['image_path'] = $this->storage->storeFile($file, $folder);
            $data['image_url'] = null;
            return;
        }

        if (is_string($url) && $url !== '') {
            $this->storage->deleteModelImagePath($existing);
            $data['image_path'] = null;
            $data['image_url'] = $url;
            return;
        }

        if (array_key_exists('image_url', $data) && $url === null) {
            $this->storage->deleteModelImagePath($existing);
            $data['image_path'] = null;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param class-string<Model>|Model $modelOrClass
     * @return array<string, mixed>
     */
    public function prepareDataForPersistence(array $data, string|Model $modelOrClass, ?Model $existing = null): array
    {
        $model = $existing ?? (is_string($modelOrClass) ? new $modelOrClass() : $modelOrClass);
        $columns = $this->schema->getTableColumns($model);
        $allowedColumns = array_flip($columns);

        $supportsProfileImageUrl = in_array('profile_image_url', $columns, true);
        $supportsImagePath = in_array('image_path', $columns, true);
        $supportsImageUrl = in_array('image_url', $columns, true);

        if ($supportsProfileImageUrl) {
            $this->prepareProfileImageUrlData($data, $model);
        } elseif (($supportsImagePath || $supportsImageUrl) && method_exists($model, 'imageFolder')) {
            $this->handleImageData($data, $model::imageFolder(), $existing);
        }

        if (! $supportsImagePath) {
            unset($data['image_path']);
        }

        if (! $supportsImageUrl) {
            unset($data['image_url']);
        }

        if (! $supportsProfileImageUrl) {
            unset($data['profile_image_url']);
        }

        unset($data['image']);

        return array_intersect_key($data, $allowedColumns);
    }

    /** @param array<string, mixed> $data */
    private function prepareProfileImageUrlData(array &$data, Model $model): void
    {
        $file = $data['image'] ?? null;
        $url = $this->extractProfileImageUrl($data);

        if ($file instanceof UploadedFile) {
            $path = $this->storage->storeFile($file, $this->resolveFolder($model));
            $data['profile_image_url'] = $this->storage->resolveUploadedPathUrl($path);
        } elseif ($url !== null) {
            $data['profile_image_url'] = $url;
        } elseif ($this->isProfileImageExplicitlyCleared($data)) {
            $data['profile_image_url'] = null;
        }

        unset($data['image_url'], $data['image_path']);
    }

    /** @param array<string, mixed> $data */
    private function extractProfileImageUrl(array $data): ?string
    {
        $url = $data['image_url'] ?? $data['profile_image_url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /** @param array<string, mixed> $data */
    private function isProfileImageExplicitlyCleared(array $data): bool
    {
        return (array_key_exists('image_url', $data) || array_key_exists('profile_image_url', $data))
            && ($data['image_url'] ?? $data['profile_image_url'] ?? null) === null;
    }

    private function resolveFolder(Model $model): string
    {
        return method_exists($model, 'imageFolder')
            ? $model::imageFolder()
            : Str::kebab(class_basename($model));
    }
}
