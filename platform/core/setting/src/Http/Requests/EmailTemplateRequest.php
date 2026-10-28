<?php

namespace Kern\Setting\Http\Requests;

use Closure;
use Illuminate\Validation\Rule;
use Kern\Base\Facades\EmailHandler;
use Kern\Base\Rules\OnOffRule;
use Kern\Support\Http\Requests\Request;
use Twig\Error\SyntaxError;

class EmailTemplateRequest extends Request
{
    public function rules(): array
    {
        $validateSyntax = function (string $attribute, mixed $value, Closure $fail): void {
            try {
                EmailHandler::validateTemplateSyntax($value);
            } catch (SyntaxError $exception) {
                $fail($exception->getRawMessage());
            }
        };

        return [
            'email_subject' => ['bail', 'nullable', 'string', 'required_with:email_subject_key', $validateSyntax],
            'email_content' => ['bail', 'required', 'string', $validateSyntax],
            'module' => ['required', 'string', 'alpha_dash', Rule::in([$this->route('module')])],
            'template_file' => ['required', 'string', 'alpha_dash', Rule::in([$this->route('template')])],
            'email_subject_key' => ['sometimes', 'string', Rule::in([get_setting_email_subject_key((string) $this->route('type'), (string) $this->route('module'), (string) $this->route('template'))])],
            'status' => [new OnOffRule],
        ];
    }
}
