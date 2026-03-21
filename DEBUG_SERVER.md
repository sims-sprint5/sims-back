# Debug Server Errors - 500 en Digital Ocean

## Pasos para debugging en el servidor

### 1. Conexión SSH
```bash
ssh -i tu_ssh_key tu_usuario@proba16.simsgrup2.app
cd /var/www/sims-back
```

### 2. Ver logs de Laravel
```bash
# Logs en vivo
docker-compose exec api tail -f storage/logs/laravel.log

# Últimos 100 logs
docker-compose logs api --tail 100
```

### 3. Reconstruir cachés
```bash
docker-compose exec api php artisan config:cache
docker-compose exec api php artisan route:cache
docker-compose exec api php artisan view:cache
```

### 4. Verificar base de datos
```bash
docker-compose exec api php artisan tinker
# Dentro de tinker:
DB::connection()->getPdo();
DB::table('users')->count();
```

### 5. Ejecutar migraciones nuevamente
```bash
docker-compose exec api php artisan migrate --force
```

### 6. Limpiar y reiniciar
```bash
docker-compose down
docker system prune -f --all
docker-compose up -d
docker-compose exec api php artisan optimize:clear
```

### 7. Ver estado de los contenedores
```bash
docker-compose ps
docker-compose logs postgres --tail 50
```

## Causas comunes

- **APP_KEY no está definido**: Revisa el `.env` en el servidor
- **Migraciones pendientes**: Las tablas no existen
- **Base de datos rechaza conexiones**: Verifica credenciales en `.env`
- **Permisos de directorios**: El directorio `storage/` debe ser escribible por www-data
- **Out of memory**: El contenedor no tiene suficiente RAM
