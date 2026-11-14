<?php

namespace Kern\CustomField\Facades;

use Illuminate\Support\Facades\Facade;
use Kern\CustomField\Support\CustomFieldSupport;
use Kern\CustomField\Support\CustomFieldSupport as BaseCustomFieldSupport;

/**
 * @method static \Kern\CustomField\Support\CustomFieldSupport expandRuleGroup(string $groupName)
 * @method static \Kern\CustomField\Support\CustomFieldSupport registerRuleGroup(string $groupName)
 * @method static \Kern\CustomField\Support\CustomFieldSupport expandRule(string $group, string $title, string $slug, \Closure|array|string $data)
 * @method static \Kern\CustomField\Support\CustomFieldSupport registerRule(string $group, string $title, string $slug, \Closure|array|string $data)
 * @method static \Kern\CustomField\Support\CustomFieldSupport setRules(array|string|null $rules)
 * @method static \Kern\CustomField\Support\CustomFieldSupport addRule(array|string $ruleName, $value = null)
 * @method static array exportCustomFieldsData(string $morphClass, string|int|null $morphId)
 * @method static string renderRules()
 * @method static string renderCustomFieldBoxes(array $boxes)
 * @method static void renderAssets()
 * @method static bool saveCustomFields(\Illuminate\Http\Request $request, \Illuminate\Database\Eloquent\Model $data)
 * @method static bool deleteCustomFields(\Illuminate\Database\Eloquent\Model|null $data)
 * @method static \Kern\CustomField\Support\CustomFieldSupport registerModule(array|string $module)
 * @method static bool isSupportedModule(string $module)
 * @method static array supportedModules()
 * @method static array|string|null getField(\Kern\Base\Models\BaseModel $data, $key = null, $default = null)
 * @method static array|string|null getChildField(array $parentField, string $key, $default = null)
 *
 * @see CustomFieldSupport
 */
class CustomField extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BaseCustomFieldSupport::class;
    }
}
