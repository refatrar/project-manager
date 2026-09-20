<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PullRequestState: string
{
    use HasOptions;

    case Draft = 'draft';
    case Open = 'open';
    case Merged = 'merged';
    case Closed = 'closed';
}
