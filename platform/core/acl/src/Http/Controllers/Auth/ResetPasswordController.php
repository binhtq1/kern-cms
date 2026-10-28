<?php

namespace Kern\ACL\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Kern\ACL\Supports\AdminAuthData;
use Kern\ACL\Traits\ResetsPasswords;
use Kern\Base\Http\Controllers\BaseController;

class ResetPasswordController extends BaseController
{
    use ResetsPasswords;

    protected string $redirectTo = '/';

    public function __construct()
    {
        $this->middleware('guest');

        $this->redirectTo = route('dashboard.index');
    }

    public function showResetForm(Request $request, $token = null)
    {
        $this->pageTitle(trans('core/acl::auth.reset.title'));

        return app(AdminAuthData::class)->render();
    }
}
