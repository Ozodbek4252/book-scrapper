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
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            // Split only when the source makes the two parts detectable.
            $table->string('family_name')->nullable();
            $table->string('given_name')->nullable();
            $table->string('full_name');
            $table->string('full_name_latin')->nullable();
            $table->string('full_name_cyrillic')->nullable();
            // Matching key: normalized, and with name order made consistent so
            // "Abdulla Qodiriy" and "Qodiriy Abdulla" land on one row.
            $table->string('full_name_normalized')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authors');
    }
};
