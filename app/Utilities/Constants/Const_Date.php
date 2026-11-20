<?php

declare(strict_types=1);

namespace App\Utilities\Constants;

final class Const_Date
{
    public const DATETIME_FORMAT_DB = 'Y-m-d H:i:s';

    public const DATETIME_FORMAT_OUTPUT = 'Y-m-d H:i:s';

    public const DATETIME_FORMAT_JS = 'Y-m-d H:i:S';

    public const DATE_FORMAT_DB = 'Y-m-d';

    public const DATE_FORMAT_OUTPUT = 'Y-m-d';

    public const DATE_FORMAT_JS = 'Y-m-d';

    public const TIME_FORMAT_DB = 'H:i:s';

    public const TIME_FORMAT_OUTPUT = 'H:i';

    public const DATERANGE_DATE_FORMAT = 'YYYY-MM-DD';

    public const DATERANGE_DATETIME_FORMAT = 'YYYY-MM-DD HH:mm:ss';

    public const DATERANGE_SEPARATOR = ' - ';
}
