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

# Allow www-data to run certbot without password
RUN echo 'www-data ALL=(ALL) NOPASSWD: /usr/bin/certbot, /usr/sbin/nginx' >> /etc/sudoers

# 7️⃣ Instalar dependencias PHP con Composer
RUN composer install --no-dev --optimize-autoloader

# 8️⃣ Exponer puerto
EXPOSE 8000

# 9️⃣ Comando por defecto — Artisan dev server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
