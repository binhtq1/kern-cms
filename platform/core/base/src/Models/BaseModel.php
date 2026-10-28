<?php

namespace Kern\Base\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Kern\Base\Contracts\BaseModel as BaseModelContract;
use Kern\Base\Facades\MacroableModels;
use Kern\Base\Models\Concerns\HasBaseEloquentBuilder;
use Kern\Base\Models\Concerns\HasMetadata;
use Kern\Base\Models\Concerns\HasUuidsOrIntegerIds;

/**
 * @method static \Kern\Base\Models\BaseQueryBuilder query()
 */
class BaseModel extends Model implements BaseModelContract
{
    use HasBaseEloquentBuilder;
    use HasMetadata;
    use HasUuidsOrIntegerIds;

    public function __get($key)
    {
        if (MacroableModels::modelHasMacro(static::class, $method = 'get'.Str::studly($key).'Attribute')) {
            return $this->{$method}();
        }

        return parent::__get($key);
    }
}
