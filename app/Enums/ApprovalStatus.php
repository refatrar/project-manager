<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ApprovalStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
