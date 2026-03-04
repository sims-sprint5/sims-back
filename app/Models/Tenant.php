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
 * When creating a tenant always put these fields INSIDE the 'data' array:
 *
 *   Tenant::create([
 *       'id'   => 'acme',
 *       'data' => [
 *           'name'           => 'Acme Corp',
 *           'admin_name'     => 'John',
 *           'admin_email'    => 'john@acme.local',
 *           'admin_password' => '...',
 *       ],
 *   ]);
 *
 * Do NOT pass custom fields as top-level keys alongside 'data' – that causes
 * the JSON column to be overwritten and the value is silently lost.
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
