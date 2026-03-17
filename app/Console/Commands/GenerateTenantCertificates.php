<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateTenantCertificates extends Command
{
    protected $signature = 'tenants:generate-certificates';

    protected $description = 'Generate SSL certificates for all tenants using certbot';

    public function handle()
    {
        $baseDomain = env('TENANT_BASE_DOMAIN', 'localhost');
        $certEmail = env('CERT_EMAIL', 'admin@simsgrup2.app');

        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->info('No tenants found.');

            return Command::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $domain = "{$tenant->id}.{$baseDomain}";

            $this->info("Generating SSL certificate for {$domain}...");

            try {
                $command = "sudo certbot --nginx -d {$domain} --non-interactive --agree-tos -m {$certEmail}";
                shell_exec($command);

                $this->info("✓ SSL certificate generated for {$domain}");
                Log::info("SSL certificate generated for {$domain}");
            } catch (\Throwable $e) {
                $this->warn("✗ Failed to generate certificate for {$domain}: ".$e->getMessage());
                Log::warning("Failed to generate certificate for {$domain}: ".$e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
