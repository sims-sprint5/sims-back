# ======================================
# Dockerfile para Laravel 12 API + Sanctum + Spatie
# ======================================

# 1️⃣ Base PHP FPM
FROM php:8.4-fpm

# 2️⃣ Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    libpq-dev \
    libzip-dev \
    libonig-dev \
    certbot \
    python3-certbot-nginx \
    nginx \
    sudo \
    && docker-php-ext-install pdo_pgsql mbstring zip \
    && rm -rf /var/lib/apt/lists/*

# 3️⃣ Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4️⃣ Definir directorio de trabajo
WORKDIR /var/www/html

# 5️⃣ Copiar toda la aplicación al contenedor
COPY . .

# 6️⃣ Crear carpetas necesarias y dar permisos
RUN mkdir -p storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap \
    && chmod -R 775 storage bootstrap

# Allow www-data to run certbot and nginx without password
RUN echo 'www-data ALL=(ALL) NOPASSWD: /usr/bin/certbot, /usr/sbin/nginx, /usr/sbin/service' >> /etc/sudoers

# Instalar supervisor para ejecutar múltiples servicios
RUN apt-get update && apt-get install -y supervisor && rm -rf /var/lib/apt/lists/*

# 7️⃣ Crear .env temporal para el build (se reemplaza en runtime)
RUN cp .env.example .env && echo "APP_KEY=" >> .env

# 8️⃣ Instalar dependencias PHP con Composer
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts \
    && composer dump-autoload --no-dev --optimize

# 9️⃣ Configurar Nginx para proxy a PHP-FPM en puerto 8000
RUN mkdir -p /etc/nginx/sites-enabled /etc/nginx/sites-available && \
    cat > /etc/nginx/sites-available/default << 'NGINX_EOF'
server {
    listen 8000 default_server;
    server_name _;
    root /var/www/html/public;
    index index.php index.html;
    charset utf-8;
    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX_EOF
    ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default && \
    sed -i 's/^user nginx;/user www-data;/' /etc/nginx/nginx.conf && \
    sed -i 's/^    worker_processes auto;/    worker_processes 2;/' /etc/nginx/nginx.conf

# 🔟 Configurar Supervisor para ejecutar PHP-FPM y Nginx
RUN cat > /etc/supervisor/conf.d/laravel.conf << 'SUPERVISOR_EOF'
[supervisord]
nodaemon=true
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

[program:php-fpm]
command=/usr/local/sbin/php-fpm
autostart=true
autorestart=unexpected
redirect_stderr=true
stdout_logfile=/var/log/supervisor/php-fpm.log
stderr_logfile=/var/log/supervisor/php-fpm-err.log

[program:nginx]
command=/usr/sbin/nginx -g "daemon off;"
autostart=true
autorestart=unexpected
redirect_stderr=true
stdout_logfile=/var/log/supervisor/nginx.log
stderr_logfile=/var/log/supervisor/nginx-err.log
SUPERVISOR_EOF

# 1️⃣1️⃣ Exponer puertos (8000 para Nginx, 9000 para PHP-FPM)
EXPOSE 8000 9000

# 1️⃣2️⃣ Script de entrada para preparar la app y ejecutar Supervisor
RUN cat > /usr/local/bin/entrypoint.sh << 'ENTRYPOINT_EOF'
#!/bin/bash
set -e
echo "======= SIMS Backend Initialization ========="
echo "Preparing Laravel application directories..."
mkdir -p bootstrap/cache storage/logs storage/framework/{cache,sessions,views,testing}
chown -R www-data:www-data bootstrap storage public
chmod -R 775 bootstrap storage
echo "Starting services (PHP-FPM + Nginx)..."
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
ENTRYPOINT_EOF
    chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
