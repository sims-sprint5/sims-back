<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WipeAllTenants extends Command
{
    protected $signature = 'db:wipe-all {--database=pgsql : The database connection to use} {--force : Force without confirmation}';

    protected $description = 'Wipe all databases including tenant schemas';

    public function handle()
    {
        $database = $this->option('database');

        $this->warn('Wiping all databases including tenant schemas...');

        if (! $this->option('force') && ! $this->confirm('This will delete ALL data. Continue?')) {
            $this->info('Cancelled.');

            return;
        }

        try {
            // Drop all tenant schemas
            $schemas = DB::select("SELECT nspname as schemaname FROM pg_namespace WHERE nspname LIKE 'tenant_%'");

            foreach ($schemas as $schema) {
                // Validate schema name matches expected pattern for safety
                if (! $this->isValidTenantSchema($schema->schemaname)) {
                    $this->warn("Skipped invalid schema name: {$schema->schemaname}");

                    continue;
                }

                DB::statement("DROP SCHEMA IF EXISTS \"{$schema->schemaname}\" CASCADE");
                $this->info("Dropped schema: {$schema->schemaname}");
            }

            // Drop public schema and recreate it
            DB::statement('DROP SCHEMA IF EXISTS public CASCADE;');
            DB::statement('CREATE SCHEMA public;');

            $this->info('Dropped and recreated public schema');
            $this->info('✓ All databases wiped successfully!');

        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());

            return 1;
        }
    }

    private function isValidTenantSchema(string $schemaName): bool
    {
        // Validate that schema name matches pattern: tenant_<digits>
        return preg_match('/^tenant_\d+$/', $schemaName) === 1;
    }
}
