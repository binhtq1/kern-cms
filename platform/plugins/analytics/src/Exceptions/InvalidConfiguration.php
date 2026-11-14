<?php

namespace Kern\Analytics\Exceptions;

use Exception;
use Kern\Base\Facades\Html;

class InvalidConfiguration extends Exception
{
    public static function credentialsIsNotValid(): self
    {
        return new self(trans('plugins/analytics::analytics.settings.credential_is_not_valid', ['url' => Html::link('https://developers.google.com/analytics/devguides/reporting/data/v1/quickstart-client-libraries', attributes: ['target' => '_blank'])->toHtml()]));
    }

    public static function invalidPropertyId(): self
    {
        return new self(trans('plugins/analytics::analytics.settings.property_id_is_invalid'));
    }
}
