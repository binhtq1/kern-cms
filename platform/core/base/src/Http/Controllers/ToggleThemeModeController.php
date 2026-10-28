<?php

namespace Kern\Base\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Kern\ACL\Models\UserMeta;

class ToggleThemeModeController extends BaseController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate(['theme' => 'required|in:light,dark']);

        $themeMode = $request->query('theme');

        UserMeta::setMeta('theme_mode', $themeMode);

        return redirect()->back();
    }
}
