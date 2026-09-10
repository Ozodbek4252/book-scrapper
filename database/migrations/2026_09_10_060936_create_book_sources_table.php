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
        Schema::create('book_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('source_key', 64)->index();
            $table->string('external_id', 191)->nullable();
            $table->string('url', 2048);
            // Kept forever. Normalization can be re-run over this without
            // touching the network again.
            $table->json('raw_payload');
            $table->decimal('price', 12, 2)->nullable();
            $table->boolean('in_stock')->nullable();
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            $table->unique(['source_key', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_sources');
    }
};
