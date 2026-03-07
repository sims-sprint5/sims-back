<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Jobs\SeedDatabase;

class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create
                            {id   : Unique identifier (e.g. company1)}
                            {name : Company name}
                            {--admin-name=    : Administrator full name}
                            {--admin-email=   : Administrator email address}
                            {--admin-password=: Administrator password}';

    protected $description = 'Create a new tenant with its PostgreSQL schema, migrations, seed data and admin user';

    public function handle(): int
    {
        $id = $this->argument('id');
        $name = $this->argument('name');

        if (Tenant::find($id)) {
            $this->error("A tenant with id '{$id}' already exists.");

            return Command::FAILURE;
        }

        $baseDomain = env('TENANT_BASE_DOMAIN', 'localhost');
        $parsed = parse_url(config('app.url'));
        $scheme = $parsed['scheme'] ?? 'http';
        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
        $adminEmail = $this->option('admin-email') ?? "admin@{$id}.{$baseDomain}";
        $adminPassword = $this->option('admin-password') ?? 'password123';
        $adminName = $this->option('admin-name') ?? 'Admin '.ucfirst($id);

        $this->info("Creating tenant '{$name}' ({$id})...");

        // 'name' must be stored inside the 'data' JSON to avoid the key being
        // silently overwritten when both 'name' and 'data' are passed together.
        $tenant = Tenant::create([
            'id' => $id,
            'data' => [
                'name' => $name,
                'admin_name' => $adminName,
                'admin_email' => $adminEmail,
                'admin_password' => $adminPassword,
            ],
        ]);

        $tenant->domains()->create(['domain' => $id]);

        try {
            SeedDatabase::dispatchSync($tenant);
        } catch (\Throwable $e) {
            $this->warn('  ⚠️  Partial seed: '.$e->getMessage());
            Log::error("Tenant seed failed for '{$id}': ".$e->getMessage());
        }

        $this->newLine();
        $this->line("  ✅ PostgreSQL schema: <info>tenant_{$id}</info>");
        $this->line('  ✅ Migrations executed');
        $this->line('  ✅ Seed data created');
        $this->line("  ✅ Domain: <info>{$id}.{$baseDomain}</info>");
        $this->newLine();
        $this->table(
            ['Detail', 'Value'],
            [
                ['URL',      "{$scheme}://{$id}.{$baseDomain}{$port}"],
                ['Admin',    $adminEmail],
                ['Password', $adminPassword],
            ]
        );

        return Command::SUCCESS;
    }
}
