<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum GitProvider: string
{
    use HasOptions;

    case GitHub = 'github';
    case GitLab = 'gitlab';
    case Bitbucket = 'bitbucket';
    case Gitea = 'gitea';

    /**
     * Get the display label for the provider.
     */
    public function label(): string
    {
        return match ($this) {
            self::GitHub => 'GitHub',
            self::GitLab => 'GitLab',
            self::Bitbucket => 'Bitbucket',
            self::Gitea => 'Gitea',
        };
    }

    /**
     * Get the provider's REST API base URL used when no self-hosted URL is stored.
     */
    public function defaultApiUrl(): ?string
    {
        return match ($this) {
            self::GitHub => 'https://api.github.com',
            self::GitLab => 'https://gitlab.com/api/v4',
            self::Bitbucket => 'https://api.bitbucket.org/2.0',
            self::Gitea => null,
        };
    }
}
