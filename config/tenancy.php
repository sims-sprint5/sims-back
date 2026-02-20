<?php

declare(strict_types=1);

use App\Models\Tenant;
use Stancl\Tenancy\Database\Models\Domain;

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant model
    |--------------------------------------------------------------------------
    */
    'tenant_model' => Tenant::class,

    /*
    |--------------------------------------------------------------------------
    | ID Generator
    |--------------------------------------------------------------------------
    | Usar null para poder pasar IDs legibles como 'acme', 'globex'
    */
    'id_generator' => null,

    /*
    |--------------------------------------------------------------------------
    | Domain model
    |--------------------------------------------------------------------------
    */
    'domain_model' => Domain::class,

    /*
    |--------------------------------------------------------------------------
    | Central domains
    |--------------------------------------------------------------------------
    | Relevante solo si usas subdominios o dominios para tenants
    */
    'central_domains' => [
        '127.0.0.1',
        'localhost',
    ],

    /*
    |--------------------------------------------------------------------------
    | Bootstrappers
    |--------------------------------------------------------------------------
    */
    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
        // Stancl\Tenancy\Bootstrappers\RedisTenancyBootstrapper::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database tenancy
    |--------------------------------------------------------------------------
    */
    'database' => [
        'central_connection' => env('DB_CONNECTION', 'pgsql'),

        'template_tenant_connection' => null,

        'prefix' => 'tenant_',   // prefijo de schemas: tenant_acme
        'suffix' => '',

        'managers' => [
            'pgsql' => Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLSchemaManager::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache tenancy
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'tag_base' => 'tenant',
    ],

    /*
    |--------------------------------------------------------------------------
    | Filesystem tenancy
    |--------------------------------------------------------------------------
    */
    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => ['local', 'public'],
        'root_override' => [
            'local' => '%storage_path%/app/',
            'public' => '%storage_path%/app/public/',
        ],
        'suffix_storage_path' => true,
        'asset_helper_tenancy' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis tenancy
    |--------------------------------------------------------------------------
    */
    'redis' => [
        'prefix_base' => 'tenant',
        'prefixed_connections' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Features (opcional)
    |--------------------------------------------------------------------------
    */
    'features' => [
        // Stancl\Tenancy\Features\UserImpersonation::class,
        // Stancl\Tenancy\Features\TenantConfig::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant routes
    |--------------------------------------------------------------------------
    */
    'routes' => true,

    /*
    |--------------------------------------------------------------------------
    | Migration parameters
    |--------------------------------------------------------------------------
    */
    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Seeder parameters
    |--------------------------------------------------------------------------
    */
    'seeder_parameters' => [
        '--class' => 'Database\\Seeders\\Tenant\\TenantDatabaseSeeder',
    ],
];
