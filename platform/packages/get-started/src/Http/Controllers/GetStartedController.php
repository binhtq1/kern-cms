<?php

namespace Kern\GetStarted\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Kern\ACL\Models\User;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\GetStarted\Http\Requests\GetStartedRequest;
use Kern\Setting\Facades\Setting;
use Kern\Theme\Facades\ThemeOption;

class GetStartedController extends BaseController
{
    public function index(): Response|RedirectResponse
    {
        return Inertia::render('System/Setup', [
            'submit' => route('get-started.save'), 'dashboard' => route('dashboard.index'),
            'site' => ['site_title' => theme_option('site_title'), 'logo' => theme_option('logo'), 'favicon' => theme_option('favicon'),
                'primary_color' => theme_option('primary_color'), 'primary_font' => theme_option('primary_font'), 'admin_logo' => setting('admin_logo'), 'admin_favicon' => setting('admin_favicon')],
            'themeFields' => ['primary_color' => theme_option()->hasField('primary_color'), 'primary_font' => theme_option()->hasField('primary_font')],
            'fonts' => array_map(fn (string $font): array => ['value' => $font, 'label' => $font], json_decode(file_get_contents(core_path('base/resources/data/google-fonts.json')), true)),
            'user' => ['username' => request()->user()->username, 'email' => request()->user()->email],
            'media' => ['list' => request()->user()->hasPermission('media.index') ? route('media.picker') : null, 'upload' => request()->user()->hasPermission('media.index') ? route('media.picker-upload') : null],
        ]);
    }

    public function save(GetStartedRequest $request): BaseHttpResponse
    {
        $step = $request->input('step');

        $nextStep = $step + 1;

        switch ($step) {
            case 1:
                break;
            case 2:
                if (! theme_option()->hasField('primary_color')) {
                    $request->request->remove('primary_color');
                }

                if (! theme_option()->hasField('primary_font')) {
                    $request->request->remove('primary_font');
                }

                foreach ($request->except(['_token', 'step']) as $key => $value) {
                    if ($value === null) {
                        continue;
                    }

                    if (is_array($value)) {
                        $value = json_encode($value);
                    }

                    ThemeOption::setOption($key, $value);

                    if (in_array($key, ['admin_logo', 'admin_favicon'])) {
                        Setting::set($key, $value);
                    }
                }

                ThemeOption::saveOptions();

                Setting::save();

                $user = Auth::guard()->user();

                $defaultUsername = config('core.base.general.demo.account.username');
                $defaultPassword = config('core.base.general.demo.account.password');

                if (
                    $defaultUsername &&
                    $defaultPassword &&
                    $user->username != $defaultUsername &&
                    ! Hash::check($defaultPassword, $user->getAuthPassword())
                ) {
                    $nextStep = 4;
                }

                break;
            case 3:
                $user = Auth::guard()->user();

                $email = $request->input('email');

                if ($user->email !== $email) {
                    $users = User::query()
                        ->where('email', $email)
                        ->where('id', '<>', $user->id)
                        ->exists();

                    if ($users) {
                        return $this
                            ->httpResponse()
                            ->setError()
                            ->setMessage(trans('core/acl::users.email_exist'))
                            ->withInput();
                    }
                }

                $username = $request->input('username');

                if ($user->username !== $username) {
                    $users = User::query()
                        ->where('username', $username)
                        ->where('id', '<>', $user->id)
                        ->exists();

                    if ($users) {
                        return $this
                            ->httpResponse()
                            ->setError()
                            ->setMessage(trans('core/acl::users.username_exist'))
                            ->withInput();
                    }
                }

                $user->fill($request->only(['username', 'email']));
                $user->password = Hash::make($request->input('password'));

                $user->save();

                do_action(USER_ACTION_AFTER_UPDATE_PROFILE, USER_MODULE_SCREEN_NAME, $request, $user);
                do_action(USER_ACTION_AFTER_UPDATE_PASSWORD, USER_MODULE_SCREEN_NAME, $request, $user);

                event(new UpdatedContentEvent(USER_MODULE_SCREEN_NAME, $request, $user));

                break;
            case 4:
                Setting::set('is_completed_get_started', '1')->save();

                break;
        }

        return $this
            ->httpResponse()
            ->setData(['step' => $nextStep]);
    }
}
