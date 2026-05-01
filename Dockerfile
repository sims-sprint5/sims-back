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
RUN mkdir -p /etc/nginx/sites-enabled /etc/nginx/sites-available && printf 'server {\n    listen 8000 default_server;\n    server_name _;\n    root /var/www/html/public;\n    index index.php index.html;\n    charset utf-8;\n    client_max_body_size 100M;\n\n    location / {\n        try_files $uri $uri/ /index.php?$query_string;\n    }\n\n    location ~ \.php$ {\n        fastcgi_pass 127.0.0.1:9000;\n        fastcgi_index index.php;\n        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;\n        include fastcgi_params;\n    }\n\n    location ~ /\.(?!well-known).* {\n        deny all;\n    }\n}' > /etc/nginx/sites-available/default && \
    ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default && \
    sed -i 's/^user nginx;/user www-data;/' /etc/nginx/nginx.conf && \
    sed -i 's/^    worker_processes auto;/    worker_processes 2;/' /etc/nginx/nginx.conf

RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default && \
    sed -i 's/^user nginx;/user www-data;/' /etc/nginx/nginx.conf && \
    sed -i 's/^    worker_processes auto;/    worker_processes 2;/' /etc/nginx/nginx.conf

# Configurar Supervisor para ejecutar PHP-FPM y Nginx
RUN printf '[supervisord]\nnodaemon=true\nlogfile=/var/log/supervisor/supervisord.log\npidfile=/var/run/supervisord.pid\n\n[program:php-fpm]\ncommand=/usr/local/sbin/php-fpm\nautostart=true\nautorestart=unexpected\nredirect_stderr=true\nstdout_logfile=/var/log/supervisor/php-fpm.log\nstderr_logfile=/var/log/supervisor/php-fpm-err.log\n\n[program:nginx]\ncommand=/usr/sbin/nginx -g "daemon off;"\nautostart=true\nautorestart=unexpected\nredirect_stderr=true\nstdout_logfile=/var/log/supervisor/nginx.log\nstderr_logfile=/var/log/supervisor/nginx-err.log' > /etc/supervisor/conf.d/laravel.conf

# Script de entrada
RUN printf '#!/bin/bash\nset -e\necho "======= SIMS Backend Initialization ========="\necho "Preparing Laravel application directories..."\nmkdir -p bootstrap/cache storage/logs storage/framework/{cache,sessions,views,testing}\nchown -R www-data:www-data bootstrap storage public\nchmod -R 775 bootstrap storage\necho "Starting services (PHP-FPM + Nginx)..."\nexec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf' > /usr/local/bin/entrypoint.sh && \
    chmod +x /usr/local/bin/entrypoint.sh

# Exponer puertos
EXPOSE 8000 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
