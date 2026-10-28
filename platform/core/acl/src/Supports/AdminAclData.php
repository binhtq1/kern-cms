<?php

namespace Kern\ACL\Supports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Kern\ACL\Enums\UserStatusEnum;
use Kern\ACL\Models\Role;
use Kern\ACL\Models\User;
use Kern\ACL\Services\ActivateUserService;
use Kern\Base\Events\UpdatedContentEvent;
use Kern\Base\Http\Actions\DeleteResourceAction;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Base\Supports\AdminRegisteredTableData;
use Kern\Base\Supports\Language;

class AdminAclData
{
    public function bulkUsers(Request $request): BaseHttpResponse
    {
        return $this->bulk($request, true);
    }

    public function bulkRoles(Request $request): BaseHttpResponse
    {
        return $this->bulk($request, false);
    }

    protected function bulk(Request $request, bool $users): BaseHttpResponse
    {
        $class = $users ? User::class : Role::class;
        $indexQuery = app(AdminRegisteredTableData::class)->indexQuery($request);
        $input = $request->validate([
            'ids' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct', Rule::exists($class, 'id')],
            'action' => ['required', Rule::in($users ? ['delete', 'status', 'change'] : ['delete', 'change'])],
            'field' => ['required_if:action,change', 'string'],
            'value' => ['required_if:action,change', 'string', 'max:10000'],
            'status' => ['required_if:action,status', Rule::in([UserStatusEnum::ACTIVATED, UserStatusEnum::DEACTIVATED])],
        ]);
        $actor = $request->user();
        $permission = ($users ? 'users.' : 'roles.').($input['action'] === 'delete' ? 'destroy' : 'edit');
        abort_unless($actor->hasPermission($permission), 403);
        if ($input['action'] === 'change') {
            app(AdminRegisteredTableData::class)->validateValue($users ? 'users' : 'roles', $input['field'], $input['value'], $input['ids'], $class);
        }
        DB::transaction(function () use ($request, $class, $users, $input, $actor): void {
            $models = $class::query()->whereKey($input['ids'])->lockForUpdate()->get();
            abort_unless($models->count() === count($input['ids']), 422);
            foreach ($models as $model) {
                $this->authorizeBulkTarget($model, $actor, $input['action'], $input['status'] ?? null);
            }
            foreach ($models as $model) {
                if ($input['action'] === 'delete') {
                    $response = DeleteResourceAction::make($model)->execute();
                    if ($response->isError()) {
                        throw ValidationException::withMessages(['ids' => $response->getMessage()]);
                    }
                } elseif ($input['action'] === 'change') {
                    app(AdminRegisteredTableData::class)->table($users ? 'users' : 'roles')->saveBulkChanges([$model->getKey()], $input['field'], $input['value']);
                } else {
                    $service = app(ActivateUserService::class);
                    $input['status'] === UserStatusEnum::ACTIVATED ? $service->activate($model) : $service->remove($model);
                    UpdatedContentEvent::dispatch(USER_MODULE_SCREEN_NAME, $request, $model);
                }
            }
        });

        return app(BaseHttpResponse::class)->setError(false)->setNextUrl(route($users ? 'users.index' : 'roles.index', $indexQuery))->withUpdatedSuccessMessage();
    }

    public function authorizeBulkTarget(User|Role $model, User $actor, string $action, ?string $status = null): void
    {
        if ($model instanceof User) {
            abort_if(! $actor->isSuperUser() && $model->isSuperUser(), 403);
            abort_if($actor->is($model) && ($action === 'delete' || ($action === 'status' && $status === UserStatusEnum::DEACTIVATED)), 403);
        } else {
            abort_unless($this->grantable($model, $actor), 403);
        }
    }

    public function grantable(Role $role, User $actor): bool
    {
        if ($actor->isSuperUser()) {
            return true;
        }

        foreach ((array) $role->permissions as $flag => $enabled) {
            if ($enabled && (! $actor->hasPermission($flag) || in_array($flag, [ACL_ROLE_SUPER_USER, ACL_ROLE_MANAGE_SUPERS], true))) {
                return false;
            }
        }

        return true;
    }

    public function roles(User $actor): array
    {
        return Role::query()->orderBy('name')->get()->filter(fn (Role $role): bool => $this->grantable($role, $actor))
            ->map(fn (Role $role): array => ['value' => (string) $role->getKey(), 'label' => $role->name])->values()->all();
    }

    public function users(Request $request): mixed
    {
        $filters = $this->listFilters($request, true);
        $search = (string) ($filters['search'] ?? '');
        $query = User::query()->with('roles')->withExists(['activations as is_activated' => fn ($query) => $query->where('completed', true)]);
        if ($status = $filters['status'] ?? null) {
            $method = $status === UserStatusEnum::ACTIVATED ? 'whereHas' : 'whereDoesntHave';
            $query->{$method}('activations', fn ($query) => $query->where('completed', true));
        }
        $query->orderBy($filters['sort'] ?? 'id', $filters['direction'] ?? 'desc');
        if ($search !== '') {
            $query->where(fn ($query) => $query->where('username', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')->orWhere('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%'));
        }

        $filters['advanced'] = app(AdminRegisteredTableData::class)->applyFilters('users', $query, $request);
        if ($request->has('_export')) {
            return app(AdminRegisteredTableData::class)->export('users', $query, $request);
        }
        $items = $query->paginate($filters['per_page'] ?? 20)->withQueryString()->through(fn (User $user): array => [
            'id' => $user->getKey(), 'name' => $user->name, 'username' => $user->username, 'email' => $user->email,
            'created_at' => $user->created_at?->toIso8601String(), 'super_user' => $user->isSuperUser(), 'activated' => (bool) $user->is_activated,
            'can_change' => $request->user()->hasPermission('users.edit') && (! $user->isSuperUser() || $request->user()->isSuperUser()),
            'can_status' => $request->user()->hasPermission('users.edit') && ! $request->user()->is($user) && (! $user->isSuperUser() || $request->user()->isSuperUser()),
            'can_assign_role' => $request->user()->hasPermission('roles.edit') && (! $user->isSuperUser() || $request->user()->isSuperUser()),
            'role_id' => (string) ($user->roles->first()?->getKey() ?? ''),
            'roles' => $user->roles->pluck('name')->all(),
            'edit_url' => ($request->user()->hasPermission('users.edit') || $request->user()->is($user)) && (! $user->isSuperUser() || $request->user()->isSuperUser()) ? route('users.profile.view', $user) : null,
            'delete_url' => $request->user()->hasPermission('users.destroy') && ! $request->user()->is($user) && (! $user->isSuperUser() || $request->user()->isSuperUser()) ? route('users.destroy', $user) : null,
        ]);

        return ['roleOptions' => $request->user()->hasPermission('roles.edit') ? $this->roles($request->user()) : [], 'assignRoleUrl' => $request->user()->hasPermission('roles.edit') ? route('roles.assign') : null, 'tableButtons' => app(AdminRegisteredTableData::class)->buttons('users'), 'tableVisibility' => app(AdminRegisteredTableData::class)->visibility('users'), 'bulkFields' => array_values(array_filter(app(AdminRegisteredTableData::class)->fields('users'), fn ($field) => $field['name'] !== 'status')), 'items' => $items, 'filters' => $filters, 'canStatus' => $request->user()->hasPermission('users.edit'), 'search' => $search, 'title' => 'Users', 'urls' => ['bulk' => route('users.bulk'), 'index' => route('users.index'), 'create' => $request->user()->hasPermission('users.create') ? route('users.create') : null], 'kind' => 'users'];
    }

    public function roleIndex(Request $request): mixed
    {
        $filters = $this->listFilters($request, false);
        $search = (string) ($filters['search'] ?? '');
        $query = Role::query()->with('author')->withCount('users')->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc');
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $filters['advanced'] = app(AdminRegisteredTableData::class)->applyFilters('roles', $query, $request);
        if ($request->has('_export')) {
            return app(AdminRegisteredTableData::class)->export('roles', $query, $request);
        }

        return [
            'tableButtons' => app(AdminRegisteredTableData::class)->buttons('roles'),
            'tableVisibility' => app(AdminRegisteredTableData::class)->visibility('roles'),
            'bulkFields' => app(AdminRegisteredTableData::class)->fields('roles'),
            'items' => $query->paginate($filters['per_page'] ?? 20)->withQueryString()->through(fn (Role $role): array => [
                'can_change' => $request->user()->hasPermission('roles.edit') && $this->grantable($role, $request->user()),
                'created_at' => $role->created_at?->toIso8601String(), 'author' => trim($role->author->name),
                'author_url' => $role->author->exists && (! $role->author->isSuperUser() || $request->user()->isSuperUser()) && ($request->user()->isSuperUser() || $request->user()->hasPermission('users.edit') || $request->user()->getKey() === $role->author->getKey()) ? $role->author->url : null,
                'id' => $role->getKey(), 'name' => $role->name, 'description' => $role->description, 'users_count' => $role->users_count,
                'edit_url' => $request->user()->hasPermission('roles.edit') && $this->grantable($role, $request->user()) ? route('roles.edit', $role) : null,
                'delete_url' => $request->user()->hasPermission('roles.destroy') && $this->grantable($role, $request->user()) ? route('roles.destroy', $role) : null,
            ]),
            'filters' => $filters, 'search' => $search, 'title' => 'Roles', 'kind' => 'roles',
            'urls' => ['bulk' => route('roles.bulk'), 'index' => route('roles.index'), 'create' => $request->user()->hasPermission('roles.create') ? route('roles.create') : null],
        ];
    }

    protected function listFilters(Request $request, bool $users): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in([UserStatusEnum::ACTIVATED, UserStatusEnum::DEACTIVATED])],
            'sort' => ['nullable', Rule::in($users ? ['id', 'username', 'email', 'created_at', 'super_user'] : ['id', 'name', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }

    public function userForm(Request $request, ?User $user = null): array
    {
        return [
            'user' => $user ? [
                ...$user->only(['id', 'first_name', 'last_name', 'username', 'email']),
                'avatar_url' => $user->avatar_url,
                'role_id' => (string) ($user->roles()->first()?->getKey() ?? ''),
            ] : null,
            'roles' => $this->roles($request->user()),
            'canAssignRole' => $request->user()->hasPermission('roles.edit') && ($user === null || ! $user->isSuperUser() || $request->user()->isSuperUser()),
            'requiresCurrentPassword' => ! $request->user()->isSuperUser() || ($user && $request->user()->is($user)),
            'isSelf' => $user && $request->user()->is($user),
            'superActions' => $user && $request->user()->isSuperUser() && ! $request->user()->is($user) ? ['url' => route($user->isSuperUser() ? 'users.remove-super' : 'users.make-super', $user), 'revoke' => $user->isSuperUser()] : null,
            'canAvatar' => $user && ($request->user()->isSuperUser() || ($request->user()->hasPermission('users.edit') && $request->user()->is($user))),
            'preferences' => [
                'locale' => $user?->getMeta('locale', app()->getLocale()),
                'locale_direction' => $user?->getMeta('locale_direction', 'ltr'),
                'theme_mode' => $user?->getMeta('theme_mode', 'light'),
            ],
            'locales' => collect(Language::getAvailableLocales())->map(fn ($locale, $code): array => ['value' => $code, 'label' => $locale['name']])->values()->all(),
            'urls' => [
                'index' => route('users.index'), 'submit' => $user ? route('users.update-profile', $user) : route('users.store'),
                'password' => $user ? route('users.change-password', $user) : null,
                'preferences' => $user ? route('users.update-preferences', $user) : null,
                'avatar' => $user ? route('users.profile.image', $user) : null,
                'removeAvatar' => $user ? route('users.profile.image.destroy', $user) : null,
                'assign' => route('roles.assign'),
            ],
        ];
    }

    public function roleForm(Request $request, ?Role $role = null): array
    {
        $permissions = (new Role)->getAvailablePermissions();

        return [
            'role' => $role ? $role->only(['id', 'name', 'description', 'is_default', 'permissions']) : null,
            'flags' => collect($permissions)->filter(fn (array $permission): bool => $request->user()->isSuperUser() || ($request->user()->hasPermission($permission['flag']) && ! in_array($permission['flag'], [ACL_ROLE_SUPER_USER, ACL_ROLE_MANAGE_SUPERS], true)))
                ->map(fn (array $permission): array => ['value' => $permission['flag'], 'label' => trans($permission['name']), 'parent' => $permission['parent_flag'] ?? null])->values()->all(),
            'urls' => ['index' => route('roles.index'), 'submit' => $role ? route('roles.update', $role) : route('roles.store'), 'duplicate' => $role && $request->user()->hasPermission('roles.create') ? route('roles.duplicate', $role) : null],
        ];
    }
}
