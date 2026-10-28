<?php

namespace Kern\JsValidation\Facades;

use Illuminate\Support\Facades\Facade;
use Kern\JsValidation\JsValidatorFactory;

/**
 * @method static \Kern\JsValidation\Javascript\JavascriptValidator make(array $rules, array $messages = [], array $customAttributes = [], string|null $selector = null)
 * @method static \Kern\JsValidation\Javascript\JavascriptValidator formRequest($formRequest, $selector = null)
 * @method static \Kern\JsValidation\Javascript\JavascriptValidator validator(\Illuminate\Validation\Validator $validator, string|null $selector = null)
 *
 * @see JsValidatorFactory
 */
class JsValidator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'js-validator';
    }
}
