<?php

namespace Kern\Member\Http\Controllers;

use Illuminate\Support\Facades\Password;
use Kern\ACL\Traits\SendsPasswordResetEmails;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Member\Forms\Fronts\Auth\ForgotPasswordForm;
use Kern\Member\Http\Requests\Fronts\Auth\ForgotPasswordRequest;
use Kern\SeoHelper\Facades\SeoHelper;
use Kern\Theme\Facades\Theme;

class ForgotPasswordController extends BaseController
{
    use SendsPasswordResetEmails {
        SendsPasswordResetEmails::sendResetLinkEmail as parentSendResetLinkEmail;
    }

    public function showLinkRequestForm()
    {
        abort_unless(setting('member_enabled_login', true), 404);

        SeoHelper::setTitle(trans('plugins/member::member.forgot_password'));

        return Theme::scope(
            'member.auth.passwords.email',
            ['form' => ForgotPasswordForm::create()],
            'plugins/member::themes.auth.passwords.email'
        )->render();
    }

    public function sendResetLinkEmail(ForgotPasswordRequest $request)
    {
        return $this->parentSendResetLinkEmail($request);
    }

    public function broker()
    {
        return Password::broker('members');
    }
}
