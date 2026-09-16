<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Scraping\DTO\RawBook;
use Database\Factories\BookSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'book_id',
    'device_id',
    'status',
    'payload',
    'cover_path',
    'reviewed_by',
    'reviewed_at',
    'review_note',
])]
class BookSubmission extends Model
{
    /** @use HasFactory<BookSubmissionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Whether this proposes a change to a book we already hold.
     */
    public function isUpdate(): bool
    {
        return $this->book_id !== null;
    }

    /**
     * The proposed fields as the merge layer expects them.
     */
    public function toRawBook(): RawBook
    {
        return RawBook::fromArray($this->payload);
    }

    /**
     * @param  Builder<BookSubmission>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', SubmissionStatus::Pending);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'payload' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
