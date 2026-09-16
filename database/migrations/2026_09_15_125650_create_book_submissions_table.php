<?php

declare(strict_types=1);

use App\Enums\SubmissionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Books proposed by app users, held until a human decides.
     *
     * Nothing here touches the catalogue. People photograph the wrong thing,
     * mistype a title, or upload rubbish, so a submission waits in this table
     * and only reaches `books` when it is approved.
     */
    public function up(): void
    {
        Schema::create('book_submissions', function (Blueprint $table) {
            $table->id();
            // Null means "this is a book we do not have". Set means the
            // submission proposes a change to an existing book.
            $table->foreignId('book_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default(SubmissionStatus::Pending->value)->index();
            // The proposed fields, in RawBook shape.
            $table->json('payload');
            // Held on the private disk until approved, so an unreviewed
            // photograph is never publicly reachable.
            $table->string('cover_path', 2048)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_submissions');
    }
};
