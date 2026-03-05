<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

/**
 * All custom fields (name, admin_name, admin_email …) are stored inside the
 * JSON `data` column via stancl's magic getAttribute / setAttribute.
 *
 * Custom attributes must be passed as TOP-LEVEL keys when creating a tenant:
 *
 *   Tenant::create([
 *       'id'             => 'acme',
 *       'name'           => 'Acme Corp',
 *       'admin_name'     => 'John',
 *       'admin_email'    => 'john@acme.local',
 *       'admin_password' => '...',
 *   ]);
 *
 * Do NOT wrap them in a nested 'data' array – stancl's BaseTenant will
 * silently ignore the nested structure and the values will be lost.
 *
 * @property string      $id
 * @property string|null $name
 * @property string|null $admin_name
 * @property string|null $admin_email
 * @property array|null  $data
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    // -----------------------------------------------------------------------
    // Convenience accessors – these read from the data JSON via magic.
    // -----------------------------------------------------------------------

    /** Display name of the tenant organisation. */
    public function getName(): ?string
    {
        return $this->data['name'] ?? null;
    }

    /** E-mail address of the tenant's primary admin account. */
    public function getAdminEmail(): ?string
    {
        return $this->data['admin_email'] ?? null;
    }
}
