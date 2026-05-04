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

# Asegurar que www-data es propietario de todo y dar permisos
RUN chown -R www-data:www-data . && \
    mkdir -p storage/logs bootstrap/cache \
      /var/lib/nginx/body /var/lib/nginx/fastcgi_temp \
      /var/lib/nginx/proxy_temp /var/lib/nginx/scgi_temp \
      /var/lib/nginx/uwsgi_temp /var/lib/nginx/client_body_temp && \
    chown -R www-data:www-data /var/lib/nginx && \
    chmod -R 775 storage bootstrap /var/lib/nginx

# Allow www-data to run certbot, nginx, service
RUN echo 'www-data ALL=(ALL) NOPASSWD: /usr/bin/certbot, /usr/sbin/nginx, /usr/sbin/service' >> /etc/sudoers

# Crear .env temporal para el build
RUN cp .env.example .env && echo "APP_KEY=" >> .env

# Instalar dependencias PHP con Composer
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts \
    && composer dump-autoload --no-dev --optimize

# Configurar Nginx para proxy a PHP-FPM en puerto 8000
RUN mkdir -p /etc/nginx/sites-enabled /etc/nginx/sites-available && printf 'server {\n    listen 8000 default_server;\n    server_name _;\n    root /var/www/html/public;\n    index index.php index.html;\n    charset utf-8;\n    client_max_body_size 100M;\n\n    location / {\n        try_files $uri $uri/ /index.php?$query_string;\n    }\n\n    location ~ \.php$ {\n        fastcgi_pass 127.0.0.1:9000;\n        fastcgi_index index.php;\n        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;\n        include fastcgi_params;\n    }\n\n    location ~ /\.(?!well-known).* {\n        deny all;\n    }\n}' > /etc/nginx/sites-available/default && \
    ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default && \
    sed -i 's/^user nginx;/user www-data;/' /etc/nginx/nginx.conf && \
    sed -i 's/^    worker_processes auto;/    worker_processes 2;/' /etc/nginx/nginx.conf

RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default && \
    sed -i 's/^user nginx;/user www-data;/' /etc/nginx/nginx.conf && \
    sed -i 's/^    worker_processes auto;/    worker_processes 2;/' /etc/nginx/nginx.conf

# Configurar Supervisor para ejecutar PHP-FPM y Nginx (logs a stdout sin archivos)
RUN printf '[supervisord]\nnodaemon=true\nsilent=true\npidfile=/tmp/supervisord.pid\nlogfile=/tmp/supervisord.log\n\n[program:php-fpm]\ncommand=/usr/local/sbin/php-fpm\nautorestart=unexpected\nstdout_logfile=/dev/stdout\nstdout_logfile_maxbytes=0\nstderr_logfile=/dev/stderr\nstderr_logfile_maxbytes=0\n\n[program:nginx]\ncommand=/usr/sbin/nginx -g "daemon off;"\nautorestart=unexpected\nstdout_logfile=/dev/stdout\nstdout_logfile_maxbytes=0\nstderr_logfile=/dev/stderr\nstderr_logfile_maxbytes=0' > /etc/supervisor/conf.d/laravel.conf

# Script de entrada - ejecutar PHP-FPM y Nginx sin supervisor
RUN printf '#!/bin/bash\nset -e\n\necho "======= SIMS Backend Initialization =========="\n\necho "→ Preparando directorios de Laravel..."\n\n# Crear carpetas necesarias\nmkdir -p bootstrap/cache \\\n         storage/logs \\\n         storage/framework/cache \\\n         storage/framework/sessions \\\n         storage/framework/views \\\n         storage/framework/testing\n\n# Ajustar permisos con tolerancia a errores\necho "→ Aplicando permisos..."\nchmod -R 775 storage bootstrap/cache 2>/dev/null || true\nchmod -R 777 bootstrap/cache 2>/dev/null || true\n\n# Permitir acceso a directorios de nginx para usuario actual (no solo www-data)\nchmod -R 777 /var/lib/nginx 2>/dev/null || true\nchmod -R 777 /var/run 2>/dev/null || true\n\necho "✅ Directorios y permisos configurados correctamente"\n\necho "→ Iniciando PHP-FPM y Nginx..."\n/usr/local/sbin/php-fpm -D\n/usr/sbin/nginx -g "daemon off;"\n' > /usr/local/bin/entrypoint.sh && \
    chmod +x /usr/local/bin/entrypoint.sh

# Exponer puertos
EXPOSE 8000 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
