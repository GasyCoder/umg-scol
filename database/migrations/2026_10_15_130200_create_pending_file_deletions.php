<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_file_deletions', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 30);
            $table->string('path');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamps();
            $table->unique(['disk', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_file_deletions');
    }
};
