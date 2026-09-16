<?php

declare(strict_types=1);

namespace App\Enums;

enum SubmissionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }
}
