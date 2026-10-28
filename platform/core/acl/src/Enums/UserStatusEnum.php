<?php

namespace Kern\ACL\Enums;

use Illuminate\Support\HtmlString;
use Kern\Base\Facades\Html;
use Kern\Base\Supports\Enum;

/**
 * @method static UserStatusEnum ACTIVATED()
 * @method static UserStatusEnum DEACTIVATED()
 */
class UserStatusEnum extends Enum
{
    public const ACTIVATED = 'activated';

    public const DEACTIVATED = 'deactivated';

    public static $langPath = 'core/acl::users.statuses';

    public function toHtml(): HtmlString|string
    {
        return match ($this->value) {
            self::ACTIVATED => Html::tag('span', self::ACTIVATED()->label(), ['class' => 'badge bg-info text-info-fg']),
            self::DEACTIVATED => Html::tag('span', self::DEACTIVATED()->label(), ['class' => 'badge bg-warning text-warning-fg']),
            default => parent::toHtml(),
        };
    }
}
