# ======================================
# Dockerfile para Laravel 12 API + Sanctum + Spatie
# Base: PHP 8.4-FPM + Nginx + Supervisor
# ======================================

FROM php:8.4-fpm

# Instalar dependencias del sistema
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
    supervisor \
    sudo \
    && docker-php-ext-install pdo_pgsql mbstring zip \
    && rm -rf /var/lib/apt/lists/*

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Definir directorio de trabajo
WORKDIR /var/www/html

# Copiar toda la aplicación al contenedor
COPY . .

# Crear carpetas necesarias y dar permisos
RUN mkdir -p storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap \
    && chmod -R 775 storage bootstrap

# Allow www-data to run certbot, nginx, service
RUN echo 'www-data ALL=(ALL) NOPASSWD: /usr/bin/certbot, /usr/sbin/nginx, /usr/sbin/service' >> /etc/sudoers

# Crear .env temporal para el build
RUN cp .env.example .env && echo "APP_KEY=" >> .env

# Instalar dependencias PHP con Composer
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts \
    && composer dump-autoload --no-dev --optimize

# Configurar Nginx para proxy a PHP-FPM en puerto 8000
RUN mkdir -p /etc/nginx/sites-enabled /etc/nginx/sites-available && \
    tee /etc/nginx/sites-available/default > /dev/null << 'EOF'
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
EOF

RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default && \
    sed -i 's/^user nginx;/user www-data;/' /etc/nginx/nginx.conf && \
    sed -i 's/^    worker_processes auto;/    worker_processes 2;/' /etc/nginx/nginx.conf

# Configurar Supervisor para ejecutar PHP-FPM y Nginx
RUN tee /etc/supervisor/conf.d/laravel.conf > /dev/null << 'EOF'
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
EOF

# Script de entrada
RUN tee /usr/local/bin/entrypoint.sh > /dev/null << 'EOF'
#!/bin/bash
set -e
echo "======= SIMS Backend Initialization ========="
echo "Preparing Laravel application directories..."
mkdir -p bootstrap/cache storage/logs storage/framework/{cache,sessions,views,testing}
chown -R www-data:www-data bootstrap storage public
chmod -R 775 bootstrap storage
echo "Starting services (PHP-FPM + Nginx)..."
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
EOF

RUN chmod +x /usr/local/bin/entrypoint.sh

# Exponer puertos
EXPOSE 8000 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
