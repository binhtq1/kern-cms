<?php

namespace Kern\ACL\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Kern\ACL\Events\RoleAssignmentEvent;
use Kern\ACL\Events\RoleUpdateEvent;
use Kern\ACL\Http\Requests\AssignRoleRequest;
use Kern\ACL\Http\Requests\RoleCreateRequest;
use Kern\ACL\Models\Role;
use Kern\ACL\Models\User;
use Kern\ACL\Supports\AdminAclData;
use Kern\ACL\Tables\RoleTable;
use Kern\Base\Http\Controllers\BaseSystemController;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Base\Supports\Breadcrumb;
use Kern\Base\Supports\Helper;

class RoleController extends BaseSystemController
{
    protected function breadcrumb(): Breadcrumb
    {
        return parent::breadcrumb()
            ->add(
                trans('core/acl::permissions.role_permission'),
                route('roles.index')
            );
    }

    public function index(RoleTable $dataTable)
    {
        $this->pageTitle(trans('core/acl::permissions.role_permission'));
        $data = app(AdminAclData::class)->roleIndex(request());

        return is_array($data) ? Inertia::render('ACL/Index', $data) : $data;
    }

    public function destroy(Role $role)
    {
        abort_unless(app(AdminAclData::class)->grantable($role, request()->user()), 403);
        $role->delete();

        Helper::clearCache();

        return $this
            ->httpResponse()
            ->setMessage(trans('core/acl::permissions.delete_success'));
    }

    public function edit(Role $role)
    {
        $this->pageTitle(trans('core/acl::permissions.details', ['name' => $role->name]));

        abort_unless(app(AdminAclData::class)->grantable($role, request()->user()), 403);

        return Inertia::render('ACL/RoleForm', app(AdminAclData::class)->roleForm(request(), $role));
    }

    public function update(Role $role, RoleCreateRequest $request)
    {
        if (isset($role)) {
            abort_unless(app(AdminAclData::class)->grantable($role, $request->user()), 403);
        }
        $this->validateFlags($request);
        if ($request->input('is_default')) {
            Role::query()->where('id', '!=', $role->getKey())->update(['is_default' => 0]);
        }

        $role->name = $request->input('name');
        $role->permissions = $this->cleanPermission((array) $request->input('flags', []));
        $role->description = $request->input('description');
        $role->updated_by = $request->user()->getKey();
        $role->is_default = $request->boolean('is_default');
        $role->save();

        Helper::clearCache();

        event(new RoleUpdateEvent($role));

        return $this
            ->httpResponse()
            ->setPreviousRoute('roles.index')
            ->setNextRoute('roles.edit', $role->getKey())
            ->setMessage(trans('core/acl::permissions.modified_success'));
    }

    protected function validateFlags(RoleCreateRequest $request): void
    {
        $available = (new Role)->getAvailablePermissions();
        foreach ((array) $request->input('flags', []) as $flag) {
            if (! is_string($flag) || ! isset($available[$flag])) {
                throw ValidationException::withMessages(['flags' => 'Unknown permission.']);
            }
            abort_unless($request->user()->isSuperUser() || ($request->user()->hasPermission($flag) && ! in_array($flag, [ACL_ROLE_SUPER_USER, ACL_ROLE_MANAGE_SUPERS], true)), 403);
        }
    }

    protected function cleanPermission(array $permissions): array
    {
        if (! $permissions) {
            return [];
        }

        $cleanedPermissions = [];
        foreach ($permissions as $permissionName) {
            $cleanedPermissions[$permissionName] = true;
        }

        return $cleanedPermissions;
    }

    public function create()
    {
        $this->pageTitle(trans('core/acl::permissions.create_role'));

        return Inertia::render('ACL/RoleForm', app(AdminAclData::class)->roleForm(request()));
    }

    public function store(RoleCreateRequest $request)
    {
        if (isset($role)) {
            abort_unless(app(AdminAclData::class)->grantable($role, $request->user()), 403);
        }
        $this->validateFlags($request);
        if ($request->input('is_default')) {
            Role::query()->update(['is_default' => 0]);
        }

        $role = Role::query()->create([
            'name' => $request->input('name'),
            'permissions' => $this->cleanPermission((array) $request->input('flags', [])),
            'description' => $request->input('description'),
            'is_default' => $request->boolean('is_default'),
            'created_by' => $request->user()->getKey(),
            'updated_by' => $request->user()->getKey(),
        ]);

        return $this
            ->httpResponse()
            ->setPreviousRoute('roles.index')
            ->setNextRoute('roles.edit', $role->getKey())
            ->setMessage(trans('core/acl::permissions.create_success'));
    }

    public function getDuplicate(Role $role)
    {
        abort_unless(app(AdminAclData::class)->grantable($role, request()->user()), 403);
        $duplicatedRole = Role::query()->create([
            'name' => $role->name.' (Duplicate)',
            'slug' => $role->slug,
            'permissions' => $role->permissions,
            'description' => $role->description,
            'created_by' => $role->created_by,
            'updated_by' => $role->updated_by,
        ]);

        return $this->httpResponse()
            ->setPreviousRoute('roles.edit', $role->getKey())
            ->setNextRoute('roles.edit', $duplicatedRole->getKey())
            ->setMessage(trans('core/acl::permissions.duplicated_success'));
    }

    public function getJson(): array
    {
        $pl = [];
        foreach (Role::query()->get() as $role) {
            $pl[] = [
                'value' => $role->getKey(),
                'text' => $role->name,
            ];
        }

        return $pl;
    }

    public function postAssignMember(AssignRoleRequest $request): BaseHttpResponse|JsonResponse
    {
        /**
         * @var User $user
         */
        $user = User::query()->findOrFail($request->input('pk'));

        /**
         * @var Role $role
         */
        $role = Role::query()->findOrFail($request->input('value'));

        abort_unless(! $user->isSuperUser() || $request->user()->isSuperUser(), 403);
        abort_unless(app(AdminAclData::class)->grantable($role, $request->user()), 403);
        $user->roles()->sync([$role->getKey()]);

        event(new RoleAssignmentEvent($role, $user));

        $response = $this->httpResponse();

        if ($request->header('X-Inertia') && $request->wantsJson()) {
            return response()->json($response->toArray());
        }

        return $response;
    }
}
