<?php

namespace Kern\Analytics\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Kern\Analytics\Rules\AnalyticsCredentialRule;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Http\Responses\BaseHttpResponse;

class AnalyticsSettingJsonController extends BaseController
{
    public function __invoke(Request $request): BaseHttpResponse
    {
        $request->validate([
            'json' => ['required', 'file', 'mimes:json', 'max:10'],
        ]);

        $content = $request->file('json')->getContent();

        $response = $this
            ->httpResponse()
            ->setData(['content' => $content]);

        if (! Str::isJson($content)) {
            return $response
                ->setError()
                ->setMessage(__('This file is not a valid JSON file.'));
        }

        $validator = Validator::make(['content' => $content], [
            'content' => ['required', 'string', new AnalyticsCredentialRule],
        ]);

        if ($validator->fails()) {
            $response->setError()->setMessage($validator->messages()->first('content'));
        }

        return $response;
    }
}
