<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum DeploymentStage: string
{
    use HasOptions;

    case Development = 'development';
    case Staging = 'staging';
    case Production = 'production';
}
