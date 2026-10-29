<?php

namespace Kern\SocialLogin\Http\Requests\Settings;

use Illuminate\Validation\Rule;
use Kern\Base\Rules\OnOffRule;
use Kern\SocialLogin\Facades\SocialService;
use Kern\Support\Http\Requests\Request;

class SocialLoginSettingRequest extends Request
{
    public function rules(): array
    {
        $providers = SocialService::getProviders();

        $rules = [
            'social_login_style' => ['required', Rule::in(['minimal', 'default', 'basic'])],
        ];

        foreach (array_keys($providers) as $provider) {
            $rules = array_merge($rules, $this->generateRule($provider));
        }

        return $rules;
    }

    protected function generateRule(string $provider): array
    {
        $enableKey = sprintf('social_login_%s_enable', $provider);

        $rule = ['nullable', 'required_if:'.$enableKey.',1'];

        return [
            $enableKey => new OnOffRule,
            sprintf('social_login_%s_app_id', $provider) => $rule,
            sprintf('social_login_%s_app_secret', $provider) => $rule,
        ];
    }
}
