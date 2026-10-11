<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class AddGithubTargetResult
{
    private function __construct(
        private readonly bool $saved,
        private readonly string $targetName,
        private readonly string $translationKey,
    ) {
    }

    public static function saved(string $targetName): self
    {
        return new self(true, $targetName, 'targets.target_saved');
    }

    public static function notFound(string $targetName): self
    {
        return new self(false, $targetName, 'targets.target_not_found');
    }

    public static function failed(string $targetName, string $translationKey): self
    {
        return new self(false, $targetName, $translationKey);
    }

    public static function fromException(string $targetName, ConnectionException|RequestException $exception): self
    {
        if ($exception instanceof ConnectionException) {
            return self::failed($targetName, 'targets.request_failed');
        }
        if ($exception->response->status() === 404) {
            return self::notFound($targetName);
        }
        return self::failed($targetName, match ($exception->response->status()) {
            401 => 'targets.invalid_token',
            403, 429 => 'targets.access_restricted',
            default => 'targets.request_failed',
        });
    }

    public function wasSaved(): bool
    {
        return $this->saved;
    }

    public function targetName(): string
    {
        return $this->targetName;
    }

    public function translationKey(): string
    {
        return $this->translationKey;
    }
}
