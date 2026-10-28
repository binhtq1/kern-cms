<?php

namespace Kern\Setting\Http\Requests;

use Closure;
use Kern\Base\Rules\OnOffRule;
use Kern\Support\Http\Requests\Request;

class EmailRulesSettingRequest extends Request
{
    public function rules(): array
    {
        $tagRules = ['bail', 'nullable', 'string', static function (string $attribute, mixed $value, Closure $fail): void {
            $tags = json_decode($value);
            if (! is_array($tags)) {
                $fail(trans('validation.array', ['attribute' => $attribute]));

                return;
            }
            foreach ($tags as $tag) {
                if (! is_object($tag) || ! isset($tag->value) || ! is_string($tag->value)) {
                    $fail(trans('validation.array', ['attribute' => $attribute]));

                    return;
                }
            }
        }];

        return [
            'email_rules_blacklist_email_domains' => $tagRules,
            'email_rules_blacklist_specified_emails' => $tagRules,
            'email_rules_exception_emails' => $tagRules,
            'email_rules_strict' => [new OnOffRule],
            'email_rules_dns' => [new OnOffRule],
            'email_rules_spoof' => [new OnOffRule],
        ];
    }
}
