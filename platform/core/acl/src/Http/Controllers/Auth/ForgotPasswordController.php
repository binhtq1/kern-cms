<?php

namespace Kern\ACL\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Kern\ACL\Supports\AdminAuthData;
use Kern\ACL\Traits\SendsPasswordResetEmails;
use Kern\Base\Http\Controllers\BaseController;

class ForgotPasswordController extends BaseController
{
    use SendsPasswordResetEmails;

    public function __construct()
    {
        $this->middleware('guest');
    }

    public function showLinkRequestForm()
    {
        $this->pageTitle(trans('core/acl::auth.forgot_password.title'));

        return app(AdminAuthData::class)->render();
    }

    protected function sendResetLinkResponse(Request $request, $response)
    {
        return $this
            ->httpResponse()
            ->setMessage(trans($response))
            ->toResponse($request);
    }
}
