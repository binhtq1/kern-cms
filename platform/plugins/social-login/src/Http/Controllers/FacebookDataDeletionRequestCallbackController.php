<?php

namespace Kern\SocialLogin\Http\Controllers;

use Illuminate\Support\Str;
use Kern\Base\Http\Controllers\BaseController;
use Kern\SocialLogin\Http\Requests\FacebookDataDeletionRequestCallbackRequest;
use Kern\SocialLogin\Supports\FacebookDataDeletionSignedRequestParser;

class FacebookDataDeletionRequestCallbackController extends BaseController
{
    public function store(
        FacebookDataDeletionRequestCallbackRequest $request,
        FacebookDataDeletionSignedRequestParser $signedRequestParser
    ) {
        $data = $signedRequestParser->parse($request->input('signed_request'));

        if (! $data) {
            return response()->json([
                'error' => 'Invalid signed request',
            ]);
        }

        return response()->json([
            'url' => route('facebook-deletion-status', ['id' => $confirmationCode = Str::uuid()]),
            'confirmation_code' => $confirmationCode,
        ]);
    }

    public function show(string $id)
    {
        abort_unless(Str::isUuid($id), 404);

        return response()->json([
            'status' => 'pending',
            'message' => 'Your data deletion request is pending. We will notify you once it is completed.',
        ]);
    }
}
