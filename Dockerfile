FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    nginx supervisor \
    freetype-dev libjpeg-turbo-dev libpng-dev \
    libzip-dev zip unzip curl oniguruma-dev \
    mariadb-client git

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
    pdo pdo_mysql gd zip mbstring exif bcmath opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Bake nginx config into image
COPY nginx/default.conf /etc/nginx/http.d/default.conf
RUN mkdir -p /run/nginx

# Supervisord (runs nginx + php-fpm together)
COPY supervisord.conf /etc/supervisord.conf

# PHP config
RUN echo "clear_env = no" >> /usr/local/etc/php-fpm.d/www.conf \
 && echo "session.save_path = /Sites/byabsayee/storage/sessions" \
        > /usr/local/etc/php/conf.d/byabsayee.ini \
 && echo "session.gc_probability = 1" \
       >> /usr/local/etc/php/conf.d/byabsayee.ini

WORKDIR /Sites/byabsayee

# Bake app code + install dependencies
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Bake build/version info into the image so About page never needs manual updates.
# Reads git metadata from the checked-out repo at build time — if .git isn't present
# (e.g. a tarball build), falls back to "dev" instead of failing the build.
RUN git config --global --add safe.directory /Sites/byabsayee \
 && if [ -d .git ]; then \
      VERSION="$(git describe --tags --always 2>/dev/null || echo dev)"; \
      COMMIT="$(git rev-parse --short HEAD 2>/dev/null || echo unknown)"; \
      BRANCH="$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo unknown)"; \
    else \
      VERSION="dev"; COMMIT="unknown"; BRANCH="unknown"; \
    fi \
 && printf '{"version":"%s","commit":"%s","branch":"%s","built_at":"%s"}\n' \
      "$VERSION" "$COMMIT" "$BRANCH" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
      > /Sites/byabsayee/build-info.json

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 1021
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
