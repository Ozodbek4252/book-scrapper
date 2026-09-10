<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sixteen characters was not enough. A shop lists a bilingual book as
     * "O'zb/Rus O'zbekcha Узб/Рус/Англ", which is 31.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('language', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('language', 16)->nullable()->change();
        });
    }
};
