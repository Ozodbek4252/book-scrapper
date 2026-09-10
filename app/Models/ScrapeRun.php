<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScrapeRunStatus;
use Database\Factories\ScrapeRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'source_key',
    'status',
    'started_at',
    'finished_at',
    'pages_scraped',
    'items_found',
    'items_new',
    'items_updated',
    'errors_count',
])]
class ScrapeRun extends Model
{
    /** @use HasFactory<ScrapeRunFactory> */
    use HasFactory;

    /**
     * @return HasMany<ScrapeError, $this>
     */
    public function errors(): HasMany
    {
        return $this->hasMany(ScrapeError::class, 'run_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ScrapeRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'pages_scraped' => 'integer',
            'items_found' => 'integer',
            'items_new' => 'integer',
            'items_updated' => 'integer',
            'errors_count' => 'integer',
        ];
    }
}
