<?php

namespace Kern\ACL\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Kern\ACL\Traits\PermissionTrait;
use Kern\Base\Casts\SafeContent;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Models\BaseModel;
use Kern\Base\Models\Concerns\HasSlug;
use Kern\Base\Supports\Helper;

class Role extends BaseModel
{
    use HasSlug;
    use PermissionTrait;

    protected $table = 'roles';

    protected $fillable = [
        'name',
        'slug',
        'permissions',
        'description',
        'is_default',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'permissions' => 'json',
        'name' => SafeContent::class,
        'description' => SafeContent::class,
        'is_default' => 'bool',
    ];

    protected static function booted(): void
    {
        self::saving(function (self $model): void {
            $model->slug = self::createSlug($model->slug ?: $model->name, $model->getKey());
        });

        self::deleted(function (self $model): void {
            $model->users()->detach();

            Helper::clearCache();
        });
    }

    public function delete(): ?bool
    {
        if ($this->exists) {
            $this->users()->detach();
        }

        return parent::delete();
    }

    public function users(): BelongsToMany
    {
        return $this
            ->belongsToMany(User::class, 'role_users', 'role_id', 'user_id')
            ->withTimestamps();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function getAvailablePermissions(): array
    {
        $permissions = [];

        $types = ['core', 'packages', 'plugins'];

        foreach ($types as $type) {
            foreach (BaseHelper::scanFolder(platform_path($type)) as $module) {
                $configuration = config(strtolower($type.'.'.$module.'.permissions'));
                if (! empty($configuration)) {
                    foreach ($configuration as $config) {
                        $permissions[$config['flag']] = $config;
                    }
                }
            }
        }

        return apply_filters('core_acl_role_permissions', $permissions);
    }
}
