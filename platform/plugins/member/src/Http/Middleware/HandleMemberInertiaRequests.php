<?php

namespace Kern\Member\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;
use Kern\Base\Facades\BaseHelper;
use Kern\Media\Facades\RvMedia;

class HandleMemberInertiaRequests extends Middleware
{
    protected $rootView = 'plugins/member::themes.dashboard.react';

    public function version(Request $request): ?string
    {
        $manifest = public_path('build/admin/manifest.json');

        if (! File::exists($manifest)) {
            return null;
        }
        $assets = json_decode(File::get($manifest), true) ?: [];
        $visited = [];
        $visit = function (string $key) use (&$visit, &$visited, $assets): void {
            if (isset($visited[$key]) || ! isset($assets[$key])) {
                return;
            }
            $visited[$key] = $assets[$key];
            foreach (array_merge($assets[$key]['imports'] ?? [], $assets[$key]['dynamicImports'] ?? []) as $import) {
                $visit($import);
            }
        };
        $visit('resources/js/member.tsx');
        ksort($visited);

        return $visited ? hash('sha256', json_encode($visited)) : null;
    }

    public function share(Request $request): array
    {
        $member = $request->user('member');
        $translations = trans('core/base::admin');
        foreach (Arr::dot(trans('plugins/member::dashboard', [], 'en')) as $key => $english) {
            if (is_string($english)) {
                $translations[$english] = trans('plugins/member::dashboard.'.$key);
            }
        }
        foreach (['Dashboard' => 'member.dashboard', 'Account settings' => 'dashboard.header_settings_link', 'Profile' => 'dashboard.header_profile_link', 'Date of birth' => 'member.dob', 'Sign out' => 'dashboard.logout-cta', 'Activity logs' => 'dashboard.activity_logs', 'No activity yet' => 'dashboard.no_activity_title', 'Email verified' => 'dashboard.verified', 'Resend verification email' => 'dashboard.resend', 'Confirm password' => 'dashboard.password_new_confirmation'] as $english => $key) {
            $translations[$english] = trans('plugins/member::'.$key);
        }

        return [
            ...parent::share($request),
            // Shared editor controls consume this presentation contract only.
            'admin' => [
                'locale' => app()->getLocale(),
                'translations' => $translations,
                'capabilities' => ['browseMedia' => false, 'uploadMedia' => true],
            ],
            'member' => [
                'id' => (string) $member->getKey(),
                'name' => $member->name,
                'avatar' => $member->avatar_url,
                'email' => $member->email,
                'verified' => (bool) $member->confirmed_at,
            ],
            'memberMediaUrlPattern' => RvMedia::url('__KERN_MEDIA_PATH__'),
            'memberUrls' => [
                'home' => BaseHelper::getHomepageUrl(),
                'dashboard' => route('public.member.dashboard'),
                'settings' => route('public.member.settings'),
                'profile' => route('public.member.post.settings'),
                'security' => route('public.member.post.security'),
                'avatar' => route('public.member.avatar'),
                'activity' => route('public.member.activity-logs'),
                'upload' => route('public.member.picker-upload'),
                'logout' => route('public.member.logout'),
                'resendConfirmation' => route('public.member.resend_confirmation', ['email' => $member->email]),
                'posts' => Route::has('public.member.posts.index') ? route('public.member.posts.index') : null,
                'createPost' => Route::has('public.member.posts.create') ? route('public.member.posts.create') : null,
                'bulkPosts' => Route::has('public.member.posts.bulk-delete') ? route('public.member.posts.bulk-delete') : null,
                'bulkUpdatePosts' => Route::has('public.member.posts.bulk-update') ? route('public.member.posts.bulk-update') : null,
            ],
        ];
    }
}
