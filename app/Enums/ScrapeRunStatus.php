<?php

declare(strict_types=1);

namespace App\Enums;

enum ScrapeRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }
}
