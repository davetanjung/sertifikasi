<?php

declare(strict_types=1);

namespace App\Utilities\Constants;

final class Const_Status
{
    // registration status for tenant
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    // rental status
    public const NEW = 'new';

    public const EXTENSION = 'extension';
}
