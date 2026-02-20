# Creating a New Module in SIMS

**Reference:** Use `app/modules/User/` as template for implementation details.

---

## Folder Structure

```
app/modules/[ModuleName]/
├── Models/[Model].php
├── Controllers/[Model]Controller.php
├── Requests/Create[Model]Request.php & Update[Model]Request.php
├── Resources/[Model]Resource.php
├── Policies/[Model]Policy.php
├── Seeders/[Model]PermissionSeeder.php
├── Providers/[ModuleName]ServiceProvider.php
├── Routes/api.php
└── database/migrations/tenant/[date]_create_[models]_table.php
```

---

## Critical: Guard Name = 'tenant'

**REQUIRED** in all permission-related files:

```php
// In Model
protected $guard_name = 'tenant';

// In Seeders
['guard_name' => 'tenant']

// In Policies
$user->hasPermissionTo('resource.action');
```

Without this, Spatie Permission searches in guard `web` (default) and won't find permissions in `tenant` guard.

---

## Steps

1. **Model** → Add `protected $guard_name = 'tenant'`
2. **Migration** → `database/migrations/tenant/` (Already added these)
3. **Requests** → Create + Update validation
4. **Resource** → JSON response format
5. **Policy** → Authorization rules using `hasPermissionTo()`
6. **Controller** → CRUD with `$this->authorize()`
7. **Routes** → `app/modules/[Module]/Routes/api.php`
8. **PermissionSeeder** → Create perms with `guard_name = 'tenant'`
9. **ServiceProvider** → Register policy + load routes
10. **config/app.php** → Register provider
11. **routes/api.php** → Add module routes
12. **TenantSeeder.php** → Add `$this->call([Model]PermissionSeeder::class)`

---

## Permission Pattern

```
[resource].[action]
```

Examples:
- `users.view.all`, `users.manage.all`, `users.delete`
- `invoices.view.all`, `invoices.create`, `invoices.delete`
- `products.view.all`, `products.manage.all`

---

## Quick Checklist

- [ ] Model with `guard_name = 'tenant'`
- [ ] Migration in `database/migrations/tenant/`
- [ ] Request classes (Create/Update)
- [ ] Resource class
- [ ] Policy with permissions
- [ ] Controller with authorization
- [ ] Routes file
- [ ] PermissionSeeder with `guard_name = 'tenant'`
- [ ] ServiceProvider
- [ ] Add provider to `config/app.php`
- [ ] Include routes in `routes/api.php`
- [ ] Add seeder to `TenantSeeder.php`
- [ ] Run: `php artisan migrate:fresh --seed`

