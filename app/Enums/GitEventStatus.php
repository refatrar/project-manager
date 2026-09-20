<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum GitEventStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
    case Ignored = 'ignored';
}
