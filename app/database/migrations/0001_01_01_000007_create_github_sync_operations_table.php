<?php

use App\Enums\GithubSyncOperationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_sync_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('github_target_id')->nullable()->constrained()->nullOnDelete();
            $table->string('target_name');
            $table->string('target_type', 16);
            $table->string('status', 32)->default(GithubSyncOperationStatus::Queued->value);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('github_target_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_sync_operations');
    }
};
