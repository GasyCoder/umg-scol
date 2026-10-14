<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establishments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('type', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_current')->default(false);
            $table->boolean('is_active')->default(true)->index();
            // NULL peut se répéter ; la valeur 1 ne peut apparaître qu'une fois.
            $table->unsignedTinyInteger('current_marker')->nullable()
                ->virtualAs('CASE WHEN is_current = 1 THEN 1 ELSE NULL END')->unique();
            $table->timestamps();
        });
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->index(['is_active', 'sort_order']);
            $table->timestamps();
        });
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->restrictOnDelete();
            $table->string('code', 30)->nullable();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unique(['establishment_id', 'code']);
            $table->index(['establishment_id', 'is_active']);
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('establishment_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('establishment_id'));
        Schema::dropIfExists('programs');
        Schema::dropIfExists('levels');
        Schema::dropIfExists('academic_years');
        Schema::dropIfExists('establishments');
    }
};
