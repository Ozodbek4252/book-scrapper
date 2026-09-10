<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('isbn13', 13)->nullable()->unique();
            $table->string('isbn10', 10)->nullable()->index();
            $table->string('title');
            // Both scripts are stored and indexed, because the same book is
            // sold as "O'tkan kunlar" on one site and "Уткан кунлар" on another.
            $table->string('title_latin')->nullable()->index();
            $table->string('title_cyrillic')->nullable()->index();
            $table->string('title_normalized')->index();
            $table->string('subtitle')->nullable();
            $table->foreignId('publisher_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('published_year')->nullable();
            $table->unsignedInteger('pages')->nullable();
            $table->string('language', 16)->nullable();
            $table->text('description')->nullable();
            $table->string('cover_url', 2048)->nullable();
            $table->string('cover_path')->nullable();
            // Fallback merge key, only set when the book has no valid ISBN:
            // sha1(normalized_title|normalized_first_author|year).
            $table->string('fingerprint', 40)->nullable()->unique();
            $table->boolean('verified')->default(false)->index();
            // Field names a human corrected by hand. The merge step never
            // overwrites these, whatever a source claims.
            $table->json('locked_fields')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
