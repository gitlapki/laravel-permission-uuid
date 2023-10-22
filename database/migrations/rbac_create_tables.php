<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

class CreatePermissionTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tableNames = config('rbac.table_names');
        $columnNames = config('rbac.column_names');

        if (empty($tableNames)) {
            throw new \Exception(
                'Error: config/rbac.php not loaded. Run [php artisan config:clear] and try again.'
            );
        }

        Schema::create($tableNames['permissions'], function (Blueprint $table) {
            $table->uuid()->primary();
            $table->string('code', 255);
            $table->string('guard_name', 255);
            $table->text('description')->nullable(true);
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['roles'], function (Blueprint $table) use ($columnNames) {
            $table->uuid()->primary();
            $table->string('code', 255);
            $table->string('guard_name', 255);
            $table->text('description')->nullable(true);
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create(
            $tableNames['model_has_permissions'],
            function (Blueprint $table) use ($tableNames, $columnNames) {
                $table->uuid(config('rbac.column_names.permission_pivot_key'));

                $table->string('model_type', 255);
                $table->uuid($columnNames['model_morph_key']);
                $table->index([$columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_model_uid_model_type_index');

                $table->foreign(config('rbac.column_names.permission_pivot_key'))
                    ->references('uuid')
                    ->on($tableNames['permissions'])
                    ->onDelete('cascade');

                $table->primary(
                    [config('rbac.column_names.permission_pivot_key'), $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary'
                );
            }
        );

        Schema::create(
            $tableNames['model_has_roles'],
            function (Blueprint $table) use ($tableNames, $columnNames) {
                $table->uuid(config('rbac.column_names.role_pivot_key'));

                $table->string('model_type', 255);
                $table->uuid($columnNames['model_morph_key']);
                $table->index([$columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_model_uid_model_type_index');

                $table->foreign(config('rbac.column_names.role_pivot_key'))
                    ->references('uuid')
                    ->on($tableNames['roles'])
                    ->onDelete('cascade');

                $table->primary([config('rbac.column_names.role_pivot_key'), $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary');
            }
        );

        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames) {
            $table->uuid(config('rbac.column_names.permission_pivot_key'));
            $table->uuid(config('rbac.column_names.role_pivot_key'));

            $table->foreign(config('rbac.column_names.permission_pivot_key'))
                ->references('uuid')
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            $table->foreign(config('rbac.column_names.role_pivot_key'))
                ->references('uuid')
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            $table->primary([config('rbac.column_names.permission_pivot_key'), config('rbac.column_names.role_pivot_key')],
                'role_has_permissions_permission_uuid_role_uid_primary');
        });

        app('cache')
            ->store(config('rbac.cache.store') != 'default' ? config('rbac.cache.store') : null)
            ->forget(config('rbac.cache.key'));
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $tableNames = config('rbac.table_names');

        if (empty($tableNames)) {
            throw new \Exception(
                'Error: config/rbac.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.'
            );
        }

        Schema::drop($tableNames['role_has_permissions']);
        Schema::drop($tableNames['model_has_roles']);
        Schema::drop($tableNames['model_has_permissions']);
        Schema::drop($tableNames['roles']);
        Schema::drop($tableNames['permissions']);
    }
}
