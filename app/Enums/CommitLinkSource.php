<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CommitLinkSource: string
{
    use HasOptions;

    case CommitMessage = 'commit_message';
    case BranchName = 'branch_name';
    case PullRequest = 'pull_request';
    case Manual = 'manual';
}
