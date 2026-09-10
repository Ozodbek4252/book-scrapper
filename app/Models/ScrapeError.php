<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScrapeStage;
use Database\Factories\ScrapeErrorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['run_id', 'url', 'stage', 'message', 'context'])]
class ScrapeError extends Model
{
    /** @use HasFactory<ScrapeErrorFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ScrapeRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ScrapeRun::class, 'run_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => ScrapeStage::class,
            'context' => 'array',
        ];
    }
}
