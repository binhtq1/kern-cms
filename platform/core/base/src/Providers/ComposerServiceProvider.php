<?php

namespace Kern\Base\Providers;

use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Auth;
use Kern\ACL\Models\User;
use Kern\Base\Supports\ServiceProvider;
use Kern\Media\Facades\RvMedia;

class ComposerServiceProvider extends ServiceProvider
{
    public function boot(Factory $view): void
    {
        $view->composer(['core/media::config'], function (): void {
            $mediaPermissions = RvMedia::getConfig('permissions', []);

            if (Auth::guard()->check()) {
                /**
                 * @var User $user
                 */
                $user = Auth::guard()->user();

                if (! $user->isSuperUser() && $user->permissions) {
                    $mediaPermissions = array_intersect(array_keys($user->permissions), $mediaPermissions);
                }
            }

            RvMedia::setPermissions($mediaPermissions);
        });
    }
}
