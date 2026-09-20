<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum GitEventType: string
{
    use HasOptions;

    case Push = 'push';
    case BranchCreated = 'branch_created';
    case BranchDeleted = 'branch_deleted';
    case PullRequestOpened = 'pull_request_opened';
    case PullRequestUpdated = 'pull_request_updated';
    case PullRequestMerged = 'pull_request_merged';
    case PullRequestClosed = 'pull_request_closed';
    case TagCreated = 'tag_created';
    case ReviewSubmitted = 'review_submitted';
}
