<?php

declare(strict_types=1);

use App\Enums\ScrapeRunStatus;
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
        Schema::create('scrape_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source_key', 64)->index();
            $table->string('status', 32)->default(ScrapeRunStatus::Pending->value);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('pages_scraped')->default(0);
            $table->unsignedInteger('items_found')->default(0);
            $table->unsignedInteger('items_new')->default(0);
            $table->unsignedInteger('items_updated')->default(0);
            $table->unsignedInteger('errors_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scrape_runs');
    }
};
