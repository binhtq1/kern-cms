<?php

namespace Kern\Member\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Kern\ACL\Traits\ResetsPasswords;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Member\Forms\Fronts\Auth\ResetPasswordForm;
use Kern\Member\Http\Requests\Fronts\Auth\ResetPasswordRequest;
use Kern\SeoHelper\Facades\SeoHelper;
use Kern\Theme\Facades\Theme;

class ResetPasswordController extends BaseController
{
    use ResetsPasswords {
        ResetsPasswords::reset as parentReset;
    }

    public string $redirectTo = '/';

    public function __construct()
    {
        $this->redirectTo = route('public.member.dashboard');
    }

    public function showResetForm(Request $request, $token = null)
    {
        abort_unless(setting('member_enabled_login', true), 404);

        SeoHelper::setTitle(__('Reset Password'));

        return Theme::scope(
            'member.auth.passwords.reset',
            [
                'token' => $token,
                'email' => $request->input('email'),
                'form' => ResetPasswordForm::create(),
            ],
            'plugins/member::themes.auth.passwords.reset'
        )->render();
    }

    public function reset(ResetPasswordRequest $request)
    {
        return $this->parentReset($request);
    }

    public function broker()
    {
        return Password::broker('members');
    }

    protected function guard()
    {
        return auth('member');
    }
}
