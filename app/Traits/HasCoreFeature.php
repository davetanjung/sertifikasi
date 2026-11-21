<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

trait HasCoreFeature
{
    use HasCanAction;
    use HasFactory;
    use HasKeywordSearch;
    use HasRoute;
    use HasUuids;

    /**
     * Get the table name of the model without keeping an instance around.
     */
    public static function getTableName(): string
    {
        return (new static)->getTable();
    }
}
