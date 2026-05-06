## Instrucciones claras para el equipo frontend

Este documento resume las acciones concretas que debe realizar el equipo frontend para que la app funcione correctamente con el backend multi-tenant.

Resumen rápido:
- Desde el dominio base `jilsoftwares.deltahost.asix2.iesmontsia.cat` el frontend debe usar los endpoints `superadmin`.
- Desde un subdominio `tenant.<base>` el frontend debe usar los endpoints `tenant`.

Requisitos previos (variables de entorno del frontend):
- `VITE_TENANT_BASE_DOMAIN` debe contener `jilsoftwares.deltahost.asix2.iesmontsia.cat`.
- `VITE_API_URL` puede apuntar al host público (`https://jilsoftwares.deltahost.asix2.iesmontsia.cat/api`) o dejarse vacío para usar rutas relativas.

1) Detección fiable del tipo de host

Reemplazar la heurística por defecto por una comprobación basada en `VITE_TENANT_BASE_DOMAIN`:

```js
const hostname = window.location.hostname;
const baseDomain = import.meta.env.VITE_TENANT_BASE_DOMAIN || 'jilsoftwares.deltahost.asix2.iesmontsia.cat';

// true si hostname es "tenant.base" (ej: acme.jilsoftwares...)
const isTenantSubdomain = hostname !== baseDomain && hostname.endsWith('.' + baseDomain);

// es central si hostname === baseDomain
const isCentral = hostname === baseDomain;
```

2) Login: elegir endpoint correcto (código a copiar)

Sustituir la llamada de login por este fragmento:

```js
const loginEndpoint = isTenantSubdomain
  ? '/api/v1/auth/login' // tenant
  : '/api/v1/superadmin/auth/login'; // central

const { data } = await axios.post(loginEndpoint, { email, password });
const token = data.token || data.access_token;
localStorage.setItem('auth_token', token);
localStorage.setItem('user_type', isTenantSubdomain ? 'tenant' : 'superadmin');

// Redirect según tipo
window.location.href = isTenantSubdomain ? '/dashboard' : '/superadmin';
```

3) Evitar llamadas tenant desde el panel central

En el montaje del panel central (superadmin), asegurarse de que **no** se llamen endpoints tenant (por ejemplo `/api/v1/vehicles`). El panel central debe usar únicamente endpoints `superadmin`, p.ej. `/api/v1/superadmin/tenants`.

Si hay componentes compartidos que cargan datos tenant por defecto, añadir una comprobación:

```js
if (localStorage.getItem('user_type') !== 'tenant') {
  // no ejecutar peticiones tenant
  return;
}
```

4) Configurar axios global (ejemplo)

En `resources/js/bootstrap.js` o fichero equivalente:

```js
import axios from 'axios';

const token = localStorage.getItem('auth_token');
window.axios = axios.create({ baseURL: import.meta.env.VITE_API_URL || '/' });
if (token) window.axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
```

5) Crear rutas/guards en el router

Configurar el router para requerir `user_type` y redirigir si el tipo no coincide con la ruta solicitada.

6) Rebuild y deploy

```bash
npm ci
npm run build
docker compose build frontend
docker compose up -d frontend
```

7) Tests y comprobaciones

- Desde dominio base: abre `https://jilsoftwares.deltahost.asix2.iesmontsia.cat/login` y verifica que el POST vaya a `/api/v1/superadmin/auth/login` y que el panel central no haga llamadas a `/api/v1/vehicles`.
- Desde subdominio: abre `https://<tenant>.<base>/login` y verifica llamadas a `/api/v1/auth/login` y posterior carga de `/api/v1/vehicles`.

8) Debugging rápido (si ves `Tenant not found for this domain.`)

- Asegúrate de que `VITE_TENANT_BASE_DOMAIN` coincide con `APP_DOMAIN` en el backend.
- Reproduce la petición y mira el log:
  ```bash
  docker compose exec -T api tail -n 200 storage/logs/laravel.log
  ```

9) Nota sobre creación de tenants

Si el backend reporta que "Tenant created but initialization failed" en la respuesta, el registro del backend contiene la razón; revisad los logs y los steps de `tenants:migrate-fresh` y `tenants:seed`.

---

Si quieres, aplico estos cambios directamente en el código frontend (busco el archivo de login y hago el patch). ¿Lo hago por ti? 
