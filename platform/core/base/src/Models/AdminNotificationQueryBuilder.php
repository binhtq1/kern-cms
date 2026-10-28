<?php

namespace Kern\Base\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AdminNotificationQueryBuilder extends BaseQueryBuilder
{
    public function hasPermission(): self
    {
        $user = Auth::guard()->user();

        if (! $user) {
            return $this->whereRaw('1 = 0');
        }

        if ($user->isSuperUser()) {
            return $this;
        }

        $permissions = $this->getModel()->newQuery()
            ->whereNotNull('permission')
            ->where('permission', '!=', '')
            ->distinct()
            ->pluck('permission')
            ->filter(fn (string $permission): bool => $user->hasPermission($permission))
            ->values()
            ->all();

        return $this->where(function (Builder $query) use ($permissions): void {
            $query->whereNull('permission')
                ->orWhere('permission', '')
                ->orWhereIn('permission', $permissions);
        });
    }
}
