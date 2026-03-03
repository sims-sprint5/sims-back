<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class DeleteTenantCommand extends Command
{
    protected $signature = 'tenant:delete {id : Identificador del tenant a eliminar}';
    protected $description = 'Elimina un tenant i el seu schema PostgreSQL complet';

    public function handle(): int
    {
        $id     = $this->argument('id');
        $tenant = Tenant::find($id);

        if (!$tenant) {
            $this->error("No s'ha trobat cap tenant amb l'id: {$id}");
            return Command::FAILURE;
        }

        $this->warn("Estàs a punt d'eliminar el tenant '{$tenant->name}' ({$id}).");
        $this->warn("Això eliminarà el schema tenant_{$id} i TOTES les seves dades.");

        if (!$this->confirm('Continuar?', false)) {
            $this->info('Operació cancel·lada.');
            return Command::SUCCESS;
        }

        $tenant->delete();

        $this->info("Tenant '{$id}' eliminat correctament.");

        return Command::SUCCESS;
    }
}
