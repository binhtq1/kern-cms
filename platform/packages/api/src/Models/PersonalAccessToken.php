<?php

namespace Kern\Api\Models;

use Kern\Base\Contracts\BaseModel;
use Kern\Base\Models\Concerns\HasBaseEloquentBuilder;
use Kern\Base\Models\Concerns\HasMetadata;
use Kern\Base\Models\Concerns\HasUuidsOrIntegerIds;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken implements BaseModel
{
    use HasBaseEloquentBuilder;
    use HasMetadata;
    use HasUuidsOrIntegerIds;
}
