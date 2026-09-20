<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum GitSyncStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Syncing = 'syncing';
    case Synced = 'synced';
    case Failed = 'failed';
}
