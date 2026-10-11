<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('github_target_id')->constrained('github_targets')->cascadeOnDelete();
            $table->unsignedBigInteger('external_id');
            $table->string('name');
            $table->string('full_name');
            $table->text('description')->nullable();
            $table->string('html_url', 2048);
            $table->string('language', 100)->nullable();
            $table->unsignedInteger('stargazers_count')->default(0);
            $table->unsignedInteger('open_issues_count')->default(0);
            $table->boolean('archived')->default(false);
            $table->timestamp('external_updated_at')->nullable();
            $table->timestamps();

            $table->unique('external_id');
            $table->index('github_target_id');
            $table->index('language');
            $table->index('archived');
            $table->index('stargazers_count');
            $table->index('external_updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_repositories');
    }
};
