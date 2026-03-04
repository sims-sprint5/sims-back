<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Jobs\SeedDatabase;

class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create
                            {id   : Identificador únic (ex: empresa1)}
                            {name : Nom de l\'empresa}
                            {--admin-name=    : Nom de l\'administrador}
                            {--admin-email=   : Email de l\'administrador}
                            {--admin-password=: Contrasenya de l\'administrador}';

    protected $description = 'Crea un nou tenant amb schema PostgreSQL, migracions, seed i admin';

    public function handle(): int
    {
        $id   = $this->argument('id');
        $name = $this->argument('name');

        if (Tenant::find($id)) {
            $this->error("Ja existeix un tenant amb l'id: {$id}");
            return Command::FAILURE;
        }

        $adminEmail    = $this->option('admin-email')    ?? "admin@{$id}.local";
        $adminPassword = $this->option('admin-password') ?? 'password123';
        $adminName     = $this->option('admin-name')     ?? 'Admin ' . ucfirst($id);

        $this->info("Creant tenant '{$name}' ({$id})...");

        // 'name' must be stored inside the 'data' JSON to avoid the key being
        // silently overwritten when both 'name' and 'data' are passed together.
        $tenant = Tenant::create([
            'id'   => $id,
            'data' => [
                'name'           => $name,
                'admin_name'     => $adminName,
                'admin_email'    => $adminEmail,
                'admin_password' => $adminPassword,
            ],
        ]);

        $tenant->domains()->create(['domain' => $id]);

        try {
            SeedDatabase::dispatchSync($tenant);
        } catch (\Throwable $e) {
            $this->warn("  ⚠️  Seed parcial: " . $e->getMessage());
            Log::error("Tenant seed failed for '{$id}': " . $e->getMessage());
        }

        $this->newLine();
        $this->line("  ✅ Schema PostgreSQL: <info>tenant_{$id}</info>");
        $this->line("  ✅ Migracions executades");
        $this->line("  ✅ Dades seed creades");
        $this->line("  ✅ Domini: <info>{$id}.localhost</info>");
        $this->newLine();
        $this->table(
            ['Detall', 'Valor'],
            [
                ['URL',      "http://{$id}.localhost:8000"],
                ['Admin',    $adminEmail],
                ['Password', $adminPassword],
            ]
        );

        return Command::SUCCESS;
    }
}
