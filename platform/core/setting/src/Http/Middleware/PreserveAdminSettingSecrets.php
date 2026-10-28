<?php

namespace Kern\Setting\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kern\Setting\Supports\AdminSettingData;
use Kern\SocialLogin\Facades\SocialService;
use Symfony\Component\HttpFoundation\Response;

class PreserveAdminSettingSecrets
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('PUT')) {
            $adapter = app(AdminSettingData::class);
            $keys = [
                'email_password', 'email_mail_gun_secret', 'email_ses_secret', 'email_postmark_token',
                'media_aws_secret_key', 'media_r2_secret_key', 'media_wasabi_secret_key', 'media_do_spaces_secret_key',
                'media_bunnycdn_key', 'media_backblaze_secret_key', 'media_backblaze_application_key', 'captcha_secret', 'analytics_service_account_credentials',
            ];
            if (is_plugin_active('social-login')) {
                foreach (SocialService::getProviders() as $provider => $config) {
                    foreach ($config['data'] as $name) {
                        if ($adapter->isSecret($name)) {
                            $keys[] = 'social_login_'.$provider.'_'.$name;
                        }
                    }
                }
            }
            $prefix = match (true) {
                $request->routeIs('settings.email.update') => 'email_',
                $request->routeIs('settings.media.update') => 'media_',
                $request->routeIs('social-login.settings.update') => 'social_login_',
                $request->routeIs('captcha.settings.update') => 'captcha_',
                $request->routeIs('analytics.settings.update') => 'analytics_',
                default => '__none__',
            };
            foreach ($keys as $key) {
                if (! str_starts_with($key, $prefix)) {
                    continue;
                }
                if (! $request->exists($key)) {
                    $request->merge([$key => setting($key, $adapter->emailDefault($key))]);
                }
            }
        }

        return $next($request);
    }
}
