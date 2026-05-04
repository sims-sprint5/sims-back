# ======================================
# Dockerfile para Laravel 12 API
# Multi-stage: Builder + Runtime (PHP-FPM)
# ======================================

# ===== STAGE 1: Builder (instalar Composer) =====
FROM php:8.4-fpm as builder

# Instalar solo lo necesario para Composer
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    unzip \
    zip \
    libpq-dev \
    libzip-dev \
    libonig-dev \
    && docker-php-ext-install pdo_pgsql mbstring zip \
    && rm -rf /var/lib/apt/lists/*

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /build

# Copiar solo archivos de dependencias (aprovecha docker cache)
COPY composer.json composer.lock ./

# Instalar dependencias (como root, sin restricciones)
RUN composer install \
    --prefer-dist \
    --no-dev \
    --no-interaction \
    --optimize-autoloader \
    --no-scripts

# ===== STAGE 2: Runtime (aplicación final) =====
FROM php:8.4-fpm

# Solo instalar herramientas essentials (php-fpm ya tiene librerías compiladas)
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    && rm -rf /var/lib/apt/lists/*

# Crear usuario no-root (laravel con UID 1000)
RUN useradd -m -u 1000 laravel

WORKDIR /var/www/html

# Copiar vendor desde builder (respeta ownership)
COPY --from=builder --chown=laravel:laravel /build/vendor ./vendor

# Copiar código de aplicación
COPY --chown=laravel:laravel . .

# Crear directorios de storage y bootstrap con permisos correctos
RUN mkdir -p bootstrap/cache storage/logs \
    storage/framework/{cache,sessions,views,testing} \
    && chown -R laravel:laravel bootstrap storage \
    && chmod -R 775 bootstrap storage

# Crear entrypoint (verificación de vendor + PHP-FPM)
RUN printf '#!/bin/bash\nset -e\n\necho "=== Laravel Application Startup ==="\n\n# Verificar vendor/autoload.php\nif [ ! -f vendor/autoload.php ]; then\n  echo "ERROR: vendor/autoload.php not found!"\n  echo "Directory contents:"\n  ls -la vendor/ 2>/dev/null || echo "vendor directory missing"\n  exit 1\nfi\n\necho "✓ vendor/autoload.php verified"\necho "✓ Starting PHP-FPM on port 9000..."\n\nexec /usr/local/sbin/php-fpm -F\n' > /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

# Cambiar a usuario no-root para runtime
USER laravel

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
