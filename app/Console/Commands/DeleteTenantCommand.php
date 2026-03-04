<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class DeleteTenantCommand extends Command
{
    protected $signature = 'tenant:delete {id : Tenant identifier to delete}';
    protected $description = 'Delete a tenant and its entire PostgreSQL schema';

    public function handle(): int
    {
        $id     = $this->argument('id');
        $tenant = Tenant::find($id);

        if (!$tenant) {
            $this->error("No tenant found with id: {$id}");
            return Command::FAILURE;
        }

        $this->warn("You are about to delete tenant '{$tenant->name}' ({$id}).");
        $this->warn("This will permanently drop schema tenant_{$id} and ALL its data.");

        if (!$this->confirm('Continue?', false)) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        $tenant->delete();

        $this->info("Tenant '{$id}' deleted successfully.");

        return Command::SUCCESS;
    }
}
