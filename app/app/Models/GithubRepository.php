<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GithubRepository extends Model
{
    protected $fillable = [
        'github_target_id',
        'external_id',
        'name',
        'full_name',
        'description',
        'html_url',
        'language',
        'stargazers_count',
        'open_issues_count',
        'archived',
        'external_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'external_id' => 'integer',
            'stargazers_count' => 'integer',
            'open_issues_count' => 'integer',
            'archived' => 'boolean',
            'external_updated_at' => 'datetime',
        ];
    }

    public function githubTarget(): BelongsTo
    {
        return $this->belongsTo(GithubTarget::class);
    }
}
