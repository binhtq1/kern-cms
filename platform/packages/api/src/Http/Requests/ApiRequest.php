<?php

namespace Kern\Api\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Kern\Base\Http\Responses\BaseHttpResponse;

abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator)
    {
        $errors = [];

        foreach ($validator->errors()->toArray() as $key => $message) {
            $errors[] = [
                $key => Arr::first($message),
            ];
        }

        $response = (new BaseHttpResponse)
            ->setError()
            ->setMessage('The given data is invalid')
            ->setData($errors)
            ->setCode(422);

        throw new ValidationException($validator, $response);
    }
}
