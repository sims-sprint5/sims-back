<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class ListTenantsCommand extends Command
{
    protected $signature = 'tenant:list';
    protected $description = 'Llista tots els tenants actius amb els seus dominis';

    public function handle(): int
    {
        $tenants = Tenant::with('domains')->get();

        if ($tenants->isEmpty()) {
            $this->warn('No hi ha tenants creats encara.');
            return Command::SUCCESS;
        }

        $this->table(
            ['ID', 'Nom', 'Domini', 'Creat'],
            $tenants->map(fn ($t) => [
                $t->id,
                $t->name ?? '-',
                $t->domains->pluck('domain')->join(', '),
                $t->created_at?->format('d/m/Y H:i') ?? '-',
            ])
        );

        $this->line("Total: {$tenants->count()} tenant(s)");

        return Command::SUCCESS;
    }
}
