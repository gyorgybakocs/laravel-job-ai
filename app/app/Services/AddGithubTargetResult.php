<?php

namespace App\Services;

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
