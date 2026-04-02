<?php

declare(strict_types=1);

namespace App\Modules\Core\Services\ImageUpload;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ImageSchemaService
{
    /** @var array<string, array<int, string>> */
    private static array $columnsCache = [];

    /** @return array<int, string> */
    public function getTableColumns(Model $model): array
    {
        $table = $model->getTable();

        if (! isset(self::$columnsCache[$table])) {
            self::$columnsCache[$table] = Schema::getColumnListing($table);
        }

        return self::$columnsCache[$table];
    }
}
