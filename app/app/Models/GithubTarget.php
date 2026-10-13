<?php

namespace App\Models;

use App\Enums\GithubTargetSyncStatus;
use App\Enums\GithubTargetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GithubTarget extends Model
{
    protected $fillable = [
        'name',
        'type',
        'external_id',
        'sync_status',
        'last_synced_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'type' => GithubTargetType::class,
            'external_id' => 'integer',
            'sync_status' => GithubTargetSyncStatus::class,
            'last_synced_at' => 'datetime',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this
            ->belongsToMany(User::class, 'github_target_user')
            ->using(UserGithubTarget::class)
            ->withTimestamps();
    }

    public function userSubscriptions(): HasMany
    {
        return $this->hasMany(UserGithubTarget::class);
    }

    public function repositories(): HasMany
    {
        return $this->hasMany(GithubRepository::class);
    }

    public function githubSyncOperations(): HasMany
    {
        return $this->hasMany(GithubSyncOperation::class);
    }
}
