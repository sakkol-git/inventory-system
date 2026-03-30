<?php

declare(strict_types=1);

namespace App\Modules\Core\Concerns;

/**
 * Provides a helper to escape LIKE-wildcard characters (% and _)
 * in user-supplied search terms before passing them to SQL LIKE clauses.
 */
trait EscapesSearchTerm
{
    /**
     * Escape SQL LIKE wildcard characters so user input is treated literally.
     */
    protected function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\%', '\_'], $value);
    }
}
