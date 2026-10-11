<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_target_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('github_target_id')->constrained('github_targets')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'github_target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_target_user');
    }
};
