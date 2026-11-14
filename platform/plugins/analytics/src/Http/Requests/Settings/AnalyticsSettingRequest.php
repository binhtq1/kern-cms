<?php

namespace Kern\Analytics\Http\Requests\Settings;

use Kern\Analytics\Rules\AnalyticsCredentialRule;
use Kern\Base\Rules\OnOffRule;
use Kern\Support\Http\Requests\Request;

class AnalyticsSettingRequest extends Request
{
    public function rules(): array
    {
        return [
            'google_analytics' => ['nullable', 'string', 'starts_with:G-'],
            'analytics_property_id' => ['nullable', 'string', 'size:9'],
            'analytics_service_account_credentials' => ['nullable', new AnalyticsCredentialRule],
            'analytics_dashboard_widgets' => new OnOffRule,
        ];
    }
}
