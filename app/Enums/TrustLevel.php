<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How much a source is trusted when two sources disagree about a field.
 *
 * Higher wins. Publisher sites outrank bookstores, and bookstores outrank
 * books submitted by mobile app users.
 */
enum TrustLevel: int
{
    case UserSubmission = 10;
    case Bookstore = 20;
    case Publisher = 30;

    public function outranks(self $other): bool
    {
        return $this->value > $other->value;
    }
}
