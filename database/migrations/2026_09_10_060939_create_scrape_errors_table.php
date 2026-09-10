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
        Schema::create('scrape_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('scrape_runs')->cascadeOnDelete();
            $table->string('url', 2048)->nullable();
            $table->string('stage', 16)->index();
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scrape_errors');
    }
};
