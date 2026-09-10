<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every book now carries a fingerprint, not only the ones without an ISBN.
     *
     * Most Uzbek shops publish no ISBN at all, so the fingerprint is the only
     * way a record from one of them can be recognised as a book already stored
     * from a source that did have one. That means it can no longer be unique:
     * a hardback and a paperback of one title in one year share a fingerprint
     * and are still two saleable products.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropUnique(['fingerprint']);
            $table->index('fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['fingerprint']);
            $table->unique('fingerprint');
        });
    }
};
