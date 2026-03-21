<?php

namespace Tests\Tenant;

use App\Models\SuperAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantLifecycleTest extends TestCase
{
    use WithFaker;

    private string $tenantId;

    private string $adminEmail;

    private string $adminPassword = 'Test1234!';

    private string $superadminToken;

    /** Run central migrations once per test class, not before every test. */
    private static bool $migrated = false;

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);

        if (tenancy()->tenant !== null) {
            tenancy()->end();
        }

        Auth::forgetGuards();

        return $response;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Run central migrations once per test-class execution.
        if (! self::$migrated) {
            $this->artisan('migrate', ['--force' => true]);
            self::$migrated = true;
        }

        // Use a unique tenant ID per test run to avoid collisions.
        $this->tenantId = 'ci-'.substr(md5(microtime()), 0, 8);
        $this->adminEmail = "admin@{$this->tenantId}.lvh.me";

        // Always upsert the test SuperAdmin so the password matches what we
        // will use below, even if a previous run left the record with a
        // different hash (firstOrCreate would not update the password).
        $superadmin = SuperAdmin::updateOrCreate(
            ['email' => env('SUPERADMIN_EMAIL', 'ci-admin@sims.test')],
            [
                'name' => 'CI Admin',
                'password' => Hash::make(env('SUPERADMIN_PASSWORD', 'ci-secret-123')),
            ]
        );

        // Obtain a SuperAdmin token for the protected endpoints.
        $response = $this->postJson('/api/v1/superadmin/auth/login', [
            'email' => $superadmin->email,
            'password' => env('SUPERADMIN_PASSWORD', 'ci-secret-123'),
        ]);

        $response->assertStatus(200);
        $this->superadminToken = $response->json('token');
    }

    protected function tearDown(): void
    {
        // Delete the tenant (cascade-drops the PostgreSQL schema + all data).
        Tenant::find($this->tenantId)?->delete();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------

    /** SuperAdmin login must return a Sanctum token. */
    public function test_superadmin_login_returns_token(): void
    {
        $this->assertNotEmpty($this->superadminToken);
    }

    /** Creating a tenant must return 201 with a domain assigned. */
    public function test_superadmin_can_create_tenant(): void
    {
        $response = $this->withToken($this->superadminToken)
            ->postJson('/api/v1/superadmin/tenants', [
                'id' => $this->tenantId,
                'name' => 'CI Test Company',
                'admin_email' => $this->adminEmail,
                'admin_password' => $this->adminPassword,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('tenant.id', $this->tenantId)
            ->assertJsonPath('tenant.domains.0.domain', $this->tenantId);
    }

    /** The tenant admin account created by the seeder must be able to log in. */
    public function test_tenant_admin_can_login(): void
    {
        // First create the tenant.
        $this->withToken($this->superadminToken)
            ->postJson('/api/v1/superadmin/tenants', [
                'id' => $this->tenantId,
                'name' => 'CI Test Company',
                'admin_email' => $this->adminEmail,
                'admin_password' => $this->adminPassword,
            ])
            ->assertStatus(201);

        // Then log in as the tenant admin via the tenant subdomain.
        $response = $this->postJson("http://{$this->tenantId}.lvh.me/api/v1/auth/login", [
            'email' => $this->adminEmail,
            'password' => $this->adminPassword,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['access_token', 'user']]);
    }

    /** The authenticated tenant user can update their own name. */
    public function test_tenant_user_can_update_own_name(): void
    {
        // First create the tenant.
        $this->withToken($this->superadminToken)
            ->postJson('/api/v1/superadmin/tenants', [
                'id' => $this->tenantId,
                'name' => 'CI Test Company',
                'admin_email' => $this->adminEmail,
                'admin_password' => $this->adminPassword,
            ])
            ->assertStatus(201);

        // Then log in as the tenant admin via the tenant subdomain.
        $token = $this->postJson("http://{$this->tenantId}.lvh.me/api/v1/auth/login", [
            'email' => $this->adminEmail,
            'password' => $this->adminPassword,
        ])
            ->assertStatus(200)
            ->json('data.access_token');

        $newName = 'Nuevo Nombre';

        $this->withToken($token)
            ->patchJson("http://{$this->tenantId}.lvh.me/api/v1/auth/me", [
                'name' => $newName,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', $newName);
    }

    /** A normal tenant user can view geofences for map rendering (read-only). */
    public function test_tenant_user_can_view_geofences(): void
    {
        // Create the tenant.
        $this->withToken($this->superadminToken)
            ->postJson('/api/v1/superadmin/tenants', [
                'id' => $this->tenantId,
                'name' => 'CI Test Company',
                'admin_email' => $this->adminEmail,
                'admin_password' => $this->adminPassword,
            ])
            ->assertStatus(201);

        // Login as tenant admin to create a geofence.
        $adminToken = $this->postJson("http://{$this->tenantId}.lvh.me/api/v1/auth/login", [
            'email' => $this->adminEmail,
            'password' => $this->adminPassword,
        ])
            ->assertStatus(200)
            ->json('data.access_token');

        $geofenceResponse = $this->withToken($adminToken)
            ->postJson("http://{$this->tenantId}.lvh.me/api/v1/geofences", [
                'name' => 'Zona 1',
                'description' => 'Zona de prueba',
                'type' => 'restricted',
                'center_latitude' => 40.4168,
                'center_longitude' => -3.7038,
                'radius' => 150,
                'status' => 'active',
            ]);

        $geofenceResponse->assertStatus(201);
        $geofenceId = (string) $geofenceResponse->json('geofence_id');

        // Register + login as a normal user.
        $userEmail = "user@{$this->tenantId}.lvh.me";
        $userPassword = 'Test1234!';

        $this->postJson("http://{$this->tenantId}.lvh.me/api/v1/auth/register", [
            'name' => 'Usuario Normal',
            'email' => $userEmail,
            'password' => $userPassword,
            'password_confirmation' => $userPassword,
        ])
            ->assertStatus(201);

        $userToken = $this->postJson("http://{$this->tenantId}.lvh.me/api/v1/auth/login", [
            'email' => $userEmail,
            'password' => $userPassword,
        ])
            ->assertStatus(200)
            ->json('data.access_token');

        // User can list geofences.
        $this->withToken($userToken)
            ->getJson("http://{$this->tenantId}.lvh.me/api/v1/geofences")
            ->assertStatus(200)
            ->assertJsonFragment(['name' => 'Zona 1']);

        // User can view a geofence.
        $this->withToken($userToken)
            ->getJson("http://{$this->tenantId}.lvh.me/api/v1/geofences/{$geofenceId}")
            ->assertStatus(200)
            ->assertJsonPath('name', 'Zona 1');

        // User cannot include logs.
        $this->withToken($userToken)
            ->getJson("http://{$this->tenantId}.lvh.me/api/v1/geofences/{$geofenceId}?include_logs=1")
            ->assertStatus(403);
    }

    /** A SuperAdmin token must be rejected on tenant routes (and vice-versa). */
    public function test_superadmin_token_denied_on_tenant_routes(): void
    {
        $this->withToken($this->superadminToken)
            ->postJson('/api/v1/superadmin/tenants', [
                'id' => $this->tenantId,
                'name' => 'CI Test Company',
                'admin_email' => $this->adminEmail,
                'admin_password' => $this->adminPassword,
            ])
            ->assertStatus(201);

        // The SuperAdmin Sanctum token must not authenticate on tenant routes.
        $response = $this->withToken($this->superadminToken)
            ->getJson("http://{$this->tenantId}.lvh.me/api/v1/auth/me");

        $response->assertStatus(401);
    }

    /** A tenant user can send and list ticket messages; other users are forbidden. */
    public function test_tenant_user_can_send_and_list_ticket_messages(): void
    {
        // Create the tenant.
        $this->withToken($this->superadminToken)
            ->postJson('/api/v1/superadmin/tenants', [
                'id' => $this->tenantId,
                'name' => 'CI Test Company',
                'admin_email' => $this->adminEmail,
                'admin_password' => $this->adminPassword,
            ])
            ->assertStatus(201);

        // Register + login as a normal user.
        $userEmail = "user1@{$this->tenantId}.lvh.me";
        $userPassword = 'Test1234!';

        $this->postJson("http://{$this->tenantId}.lvh.me/api/v1/auth/register", [
            'name' => 'Usuario Normal 1',
            'email' => $userEmail,
            'password' => $userPassword,
            'password_confirmation' => $userPassword,
        ])
            ->assertStatus(201);

        $userToken = $this->postJson("http://{$this->tenantId}.lvh.me/api/v1/auth/login", [
            'email' => $userEmail,
            'password' => $userPassword,
        ])
            ->assertStatus(200)
            ->json('data.access_token');

        // Create a ticket as this user.
        $ticketResponse = $this->withToken($userToken)
            ->postJson("http://{$this->tenantId}.lvh.me/api/v1/tickets", [
                'type' => 'technical',
                'subject' => 'No funciona',
                'description' => 'Detalle del problema',
                'priority' => 'medium',
            ]);

        $ticketResponse->assertStatus(201);
        $ticketId = (string) $ticketResponse->json('ticket_id');
        $this->assertNotEmpty($ticketId);

        // Send a message.
        $messageText = 'Hola soporte, tengo una duda.';

        $this->withToken($userToken)
            ->postJson("http://{$this->tenantId}.lvh.me/api/v1/tickets/{$ticketId}/messages", [
                'message' => $messageText,
            ])
            ->assertStatus(201)
            ->assertJsonFragment(['message' => $messageText]);

        // List messages should include it.
        $this->withToken($userToken)
            ->getJson("http://{$this->tenantId}.lvh.me/api/v1/tickets/{$ticketId}/messages")
            ->assertStatus(200)
            ->assertJsonFragment(['message' => $messageText]);

        // Another user must not see messages for this ticket.
        $userEmail2 = "user2@{$this->tenantId}.lvh.me";

        $this->postJson("http://{$this->tenantId}.lvh.me/api/v1/auth/register", [
            'name' => 'Usuario Normal 2',
            'email' => $userEmail2,
            'password' => $userPassword,
            'password_confirmation' => $userPassword,
        ])
            ->assertStatus(201);

        $userToken2 = $this->postJson("http://{$this->tenantId}.lvh.me/api/v1/auth/login", [
            'email' => $userEmail2,
            'password' => $userPassword,
        ])
            ->assertStatus(200)
            ->json('data.access_token');

        $this->withToken($userToken2)
            ->getJson("http://{$this->tenantId}.lvh.me/api/v1/tickets/{$ticketId}/messages")
            ->assertStatus(403);
    }

    /** Deleting a tenant must remove it from the central DB. */
    public function test_superadmin_can_delete_tenant(): void
    {
        $this->withToken($this->superadminToken)
            ->postJson('/api/v1/superadmin/tenants', [
                'id' => $this->tenantId,
                'name' => 'CI Test Company',
                'admin_email' => $this->adminEmail,
                'admin_password' => $this->adminPassword,
            ])
            ->assertStatus(201);

        $this->withToken($this->superadminToken)
            ->deleteJson("/api/v1/superadmin/tenants/{$this->tenantId}")
            ->assertStatus(200);

        $this->assertNull(Tenant::find($this->tenantId));

        // Prevent tearDown from trying to delete it again.
        $this->tenantId = 'already-deleted-'.$this->tenantId;
    }

    /** Data created in one tenant must not be visible in another. */
    public function test_tenant_data_is_isolated(): void
    {
        $tenantIdB = $this->tenantId.'b';
        $adminEmailB = "admin@{$tenantIdB}.lvh.me";

        // Create tenant A.
        $this->withToken($this->superadminToken)
            ->postJson('/api/v1/superadmin/tenants', [
                'id' => $this->tenantId,
                'name' => 'CI Tenant A',
                'admin_email' => $this->adminEmail,
                'admin_password' => $this->adminPassword,
            ])
            ->assertStatus(201);

        // Create tenant B.
        $this->withToken($this->superadminToken)
            ->postJson('/api/v1/superadmin/tenants', [
                'id' => $tenantIdB,
                'name' => 'CI Tenant B',
                'admin_email' => $adminEmailB,
                'admin_password' => $this->adminPassword,
            ])
            ->assertStatus(201);

        // Log in as admin of tenant A and fetch its user list.
        $tokenA = $this->postJson("http://{$this->tenantId}.lvh.me/api/v1/auth/login", [
            'email' => $this->adminEmail,
            'password' => $this->adminPassword,
        ])
            ->assertStatus(200)
            ->json('data.access_token');

        $usersA = $this->withToken($tokenA)
            ->getJson("http://{$this->tenantId}.lvh.me/api/v1/users")
            ->assertStatus(200)
            ->json('data');

        // Log in as admin of tenant B and fetch its user list.
        $tokenB = $this->postJson("http://{$tenantIdB}.lvh.me/api/v1/auth/login", [
            'email' => $adminEmailB,
            'password' => $this->adminPassword,
        ])
            ->assertStatus(200)
            ->json('data.access_token');

        $usersB = $this->withToken($tokenB)
            ->getJson("http://{$tenantIdB}.lvh.me/api/v1/users")
            ->assertStatus(200)
            ->json('data');

        // Extract email lists.
        $emailsA = array_column($usersA, 'email');
        $emailsB = array_column($usersB, 'email');

        // No user from A should appear in B (and vice-versa).
        $this->assertEmpty(
            array_intersect($emailsA, $emailsB),
            'Tenant isolation failed: users from tenant A are visible in tenant B.'
        );

        // Cleanup tenant B (tenant A is cleaned up by tearDown).
        Tenant::find($tenantIdB)?->delete();
    }
}
