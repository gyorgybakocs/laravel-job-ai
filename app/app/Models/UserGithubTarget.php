<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserGithubTarget extends Pivot
{
    protected $table = 'github_target_user';

    public $incrementing = true;

    protected $fillable = [
        'user_id',
        'github_target_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function githubTarget(): BelongsTo
    {
        return $this->belongsTo(GithubTarget::class);
    }
}
