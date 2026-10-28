<?php

namespace Kern\PluginManagement\Enums;

use Kern\Base\Supports\Enum;

class PluginFilterStatus extends Enum
{
    public const ALL = 'all';

    public const ACTIVATED = 'activated';

    public const NOT_ACTIVATED = 'not-activated';

    protected static $langPath = 'packages/plugin-management::plugin.enums.plugin_filter_status';
}
