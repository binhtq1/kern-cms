<?php

namespace Kern\Installer\Http\Middleware;

use Illuminate\Support\Facades\File;
use Kern\ACL\Models\User;
use Throwable;

abstract class InstallerMiddleware
{
    public function alreadyInstalled(): bool
    {
        if (! config('packages.installer.installer.enabled')) {
            return true;
        }

        if (File::exists(storage_path('installed'))) {
            return true;
        }

        try {
            return ! File::exists(storage_path(INSTALLING_SESSION_NAME))
                && User::query()->where('super_user', true)->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
