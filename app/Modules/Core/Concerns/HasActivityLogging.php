<?php

declare(strict_types=1);

namespace App\Modules\Core\Concerns;

use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * Provides consistent activity-log configuration for all domain models.
 *
 * Automatically logs created, updated, and deleted events with dirty attributes.
 * Override getActivitylogOptions() in a model for custom behaviour.
 */
trait HasActivityLogging
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName($this->getActivityLogName())
            ->setDescriptionForEvent(
                fn (string $eventName): string => $this->getActivityLogName()." was {$eventName}",
            );
    }

    /**
     * Derive a kebab-case log name from the model class.
     * e.g. PlantSpecies → plant-species, LabNotebook → lab-notebook.
     */
    protected function getActivityLogName(): string
    {
        return str(class_basename(static::class))->snake()->replace('_', '-')->toString();
    }
}
