# Build stage for Node/assets
FROM node:22-bullseye-slim AS assets

WORKDIR /app

# Copy package files
COPY package*.json ./
COPY tsconfig.json ./
COPY vite.config.js ./
COPY components.json ./
COPY eslint.config.js ./

# Install dependencies
RUN npm ci

# Copy resources
COPY resources ./resources

# Build assets
RUN npm run build

# PHP production stage
FROM php:8.2-fpm-alpine

WORKDIR /var/www/html

# Install system and build dependencies (build deps removed after extensions built)
RUN apk add --no-cache --virtual .build-deps \
    build-base \
    autoconf \
    automake \
    libtool \
    pkgconfig \
    linux-headers \
    zlib-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libxml2-dev \
    openssl-dev \
    libzip-dev \
    g++ \
    make \
    ca-certificates \
    && apk add --no-cache \
    postgresql-client \
    postgresql-dev \
    zip \
    curl \
    git \
    bash \
    ca-certificates \
    nginx \
    supervisor

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) gd && \
    docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_pgsql \
    pdo_mysql \
    bcmath \
    xml \
    zip \
    opcache && \
    # Clean build deps to reduce image size
    apk del .build-deps && \
    rm -rf /var/cache/apk/* /tmp/* /usr/src/php/ext/*

# Configure OPcache
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.enable_cli=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.save_comments=1'; \
    echo 'opcache.fast_shutdown=1'; \
    } > $PHP_INI_DIR/conf.d/opcache.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . .

# Remove node_modules if exists
RUN rm -rf node_modules .git

# Copy built assets from assets stage
COPY --from=assets /app/public/build ./public/build

# Install PHP dependencies (no dev)
RUN composer install --optimize-autoloader --no-dev --no-interaction

# Copy configuration files
COPY nginx.conf /etc/nginx/nginx.conf
COPY supervisord.conf /etc/supervisord.conf

# Set permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 775 /var/www/html/storage \
    /var/www/html/bootstrap/cache && \
    chown -R www-data:www-data /var/lib/nginx /var/log/nginx

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://127.0.0.1:${PORT:-80}/up || exit 1

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint"]
