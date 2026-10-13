<?php

namespace App\Models;

use App\Enums\GithubSyncOperationStatus;
use App\Enums\GithubTargetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GithubSyncOperation extends Model
{
    protected $fillable = [
        'user_id',
        'github_target_id',
        'target_name',
        'target_type',
        'status',
        'error',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'target_type' => GithubTargetType::class,
            'status' => GithubSyncOperationStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function githubTarget(): BelongsTo
    {
        return $this->belongsTo(GithubTarget::class);
    }
}
