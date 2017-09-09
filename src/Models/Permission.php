<?php
declare(strict_types=1);

namespace Maklad\Permission\Models;

use Illuminate\Support\Collection;
use Jenssegers\Mongodb\Eloquent\Model;
use Jenssegers\Mongodb\Relations\BelongsToMany;
use Maklad\Permission\Contracts\PermissionInterface;
use Maklad\Permission\Exceptions\PermissionAlreadyExists;
use Maklad\Permission\Exceptions\PermissionDoesNotExist;
use Maklad\Permission\Helpers;
use Maklad\Permission\PermissionRegistrar;
use Maklad\Permission\Traits\RefreshesPermissionCache;

/**
 * Class Permission
 * @package Maklad\Permission\Models
 */
class Permission extends Model implements PermissionInterface
{
    use RefreshesPermissionCache;

    public $guarded = ['id'];
    protected $helpers;

    /**
     * Permission constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->helpers = new Helpers();
        $attributes['guard_name'] = $attributes['guard_name'] ?? $this->helpers->config('auth.defaults.guard');

        parent::__construct($attributes);

        $this->setTable($this->helpers->config('permission.table_names.permissions'));
    }

    /**
     * Create new Permission
     * @param array $attributes
     *
     * @return $this|\Illuminate\Database\Eloquent\Model
     * @throws \Maklad\Permission\Exceptions\PermissionAlreadyExists
     */
    public static function create(array $attributes = [])
    {
        $helpers = new Helpers();
        $attributes['guard_name'] = $attributes['guard_name'] ?? $helpers->config('auth.defaults.guard');

        if (static::getPermissions()->where('name', $attributes['name'])->where(
            'guard_name',
            $attributes['guard_name']
        )->first()) {
            $name = $attributes['name'];
            $guardName = $attributes['guard_name'];
            throw new PermissionAlreadyExists($helpers->getPermissionAlreadyExistsMessage($name, $guardName));
        }

        return static::query()->create($attributes);
    }

    /**
     * A permission can be applied to roles.
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            $this->helpers->config('permission.models.role'),
            $this->helpers->config('permission.table_names.role_has_permissions')
        );
    }

    /**
     * A permission belongs to some users of the model associated with its guard.
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany($this->helpers->getModelForGuard($this->attributes['guard_name']));
    }

    /**
     * Find a permission by its name (and optionally guardName).
     *
     * @param string $name
     * @param string|null $guardName
     *
     * @return PermissionInterface
     * @throws PermissionDoesNotExist
     */
    public static function findByName(string $name, $guardName = null): PermissionInterface
    {
        $helpers = new Helpers();
        $guardName = $guardName ?? $helpers->config('auth.defaults.guard');

        $permission = static::getPermissions()->where('name', $name)->where('guard_name', $guardName)->first();

        if (! $permission) {
            throw new PermissionDoesNotExist($helpers->getPermissionDoesNotExistMessage($name, $guardName));
        }

        return $permission;
    }

    /**
     * Get the current cached permissions.
     * @return Collection
     */
    protected static function getPermissions(): Collection
    {
        $helpers = new Helpers();
        return $helpers->app(PermissionRegistrar::class)->getPermissions();
    }
}
