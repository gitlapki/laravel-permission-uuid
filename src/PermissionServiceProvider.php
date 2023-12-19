<?php

namespace Spatie\Permission;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Cache\Factory as FactoryContract;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Contracts\Role as RoleContract;

use Spatie\Permission\Repositories\RoleRepositoryInterface;
use Spatie\Permission\Repositories\RoleRepository;
use Spatie\Permission\Repositories\PermissionRepositoryInterface;
use Spatie\Permission\Repositories\PermissionRepository;
use Spatie\Permission\Services\CacheRepositoryInterface;
use Spatie\Permission\Services\CacheRepository;

class PermissionServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $cacheManager = $this->app->make(abstract: FactoryContract::class);

        $this->app->singleton(RoleRepositoryInterface::class, function ($app) use ($cacheManager) {
            return new RoleRepository();
        });
        $roleRepository = $this->app->make(abstract: RoleRepositoryInterface::class);

        $this->app->singleton(PermissionRepositoryInterface::class, function ($app) use ($cacheManager) {
            return new PermissionRepository();
        });
        $permissionRepository = $this->app->make(abstract: PermissionRepositoryInterface::class);

        $this->app->singleton(CacheRepositoryInterface::class, function ($app) use ($cacheManager) {
            return new CacheRepository(cacheManager: $cacheManager);
        });
        $cacheService = $this->app->make(abstract: CacheRepositoryInterface::class);

        $this->app->singleton(
            PermissionService::class,
            function ($app) use ($roleRepository, $permissionRepository, $cacheService) {
                return new PermissionService(
                    roleRepository: $roleRepository,
                    permissionRepository: $permissionRepository,
                    cacheService: $cacheService,
                );
            }
        );


        $this->offerPublishing();

        $this->registerMacroHelpers();

        $this->registerCommands();

        $this->registerModelBindings();

        if (config('rbac.register_permission_check_method')) {
            app(Gate::class)->before(
                function (Authorizable $user, string $ability) {
                    if (method_exists($user, 'checkPermissionTo')) {
                        return $user->checkPermissionTo($ability) ?: null;
                    }
                }
            );
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/rbac.php',
            'rbac'
        );
    }

    protected function offerPublishing()
    {
        $this->publishes(
            paths: [
                __DIR__ . '/../config/rbac.php' => config_path('rbac.php'),
            ],
            groups: 'rbac-config'
        );

        $this->publishes(
            paths: [
                __DIR__ . '/../database/migrations/rbac_create_tables.php'
                => $this->getMigrationFileName('rbac_create_tables.php'),
            ],
            groups: 'rbac-migrations'
        );
    }

    protected function registerCommands()
    {
        $this->commands([
            Commands\CacheReset::class,
            Commands\CreateRole::class,
            Commands\CreatePermission::class,
            Commands\Show::class,
        ]);
    }

    protected function registerModelBindings()
    {
        $this->app->bind(PermissionContract::class, config('rbac.models.permission'));
        $this->app->bind(RoleContract::class, config('rbac.models.role'));
    }

    protected function registerMacroHelpers()
    {
        Route::macro('rbacRole', function ($roles = []) {
            $roles = implode('|', Arr::wrap($roles));

            $this->middleware("rbac_role:$roles");

            return $this;
        });

        Route::macro('rbacPermission', function ($permissions = []) {
            $permissions = implode('|', Arr::wrap($permissions));

            $this->middleware("rbac_permission:$permissions");

            return $this;
        });
    }

    /**
     * Returns existing migration file if found, else uses the current timestamp.
     */
    protected function getMigrationFileName($migrationFileName): string
    {
        $timestamp = date('Y_m_d_His');

        $filesystem = $this->app->make(Filesystem::class);

        return Collection::make($this->app->databasePath() . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR)
            ->flatMap(function ($path) use ($filesystem, $migrationFileName) {
                return $filesystem->glob($path . '*_' . $migrationFileName);
            })
            ->push($this->app->databasePath() . "/migrations/{$timestamp}_{$migrationFileName}")
            ->first();
    }
}
