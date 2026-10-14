<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->restrictOnDelete();
            $table->string('code', 30)->nullable();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['establishment_id', 'code']);
            $table->unique(['id', 'establishment_id'], 'mentions_id_establishment_unique');
            $table->index(['establishment_id', 'is_active']);
        });
        Schema::table('programs', function (Blueprint $table) {
            // Optional: existing programs have no mention until an administrator assigns one.
            $table->unsignedBigInteger('mention_id')->nullable()->after('establishment_id');
            // A program and its mention always belong to the same establishment.
            $table->foreign(['mention_id', 'establishment_id'], 'programs_mention_establishment_foreign')->references(['id', 'establishment_id'])->on('mentions')->restrictOnDelete();
            $table->index('mention_id');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropForeign('programs_mention_establishment_foreign');
            $table->dropIndex(['mention_id']);
            $table->dropColumn('mention_id');
        });
        Schema::dropIfExists('mentions');
    }
};
