# ==========================================
# STAGE 1: Backend afhankelijkheden (Composer)
# ==========================================
FROM composer:2 AS backend
WORKDIR /app

# Kopieer de bestanden en installeer PHP dependencies voor productie
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

# ==========================================
# STAGE 2: Frontend bouwen (Node.js & Vite)
# ==========================================
FROM node:20-alpine AS frontend
WORKDIR /app

# Kopieer package files en installeer dependencies
COPY package.json package-lock.json ./
RUN npm ci

# Kopieer de rest van de applicatie
COPY . .

# CRUCIAAL: Kopieer de vendor map vanuit de backend stage, 
# zodat Vite de Filament CSS bestanden kan vinden!
COPY --from=backend /app/vendor /app/vendor

# Bouw de frontend assets
RUN npm run build

# ==========================================
# STAGE 3: De uiteindelijke Productie Image
# ==========================================
FROM php:8.5-apache

# Installeer benodigde systeem-packages en PHP extensies voor Laravel & MySQL
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql zip intl \
    && rm -rf /var/lib/apt/lists/*

# Verhoog de PHP upload-limieten (standaard 2M) zodat gecomprimeerde
# foto-uploads van het inzendformulier niet stilletjes geweigerd worden
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-uploads.ini"

# Activeer Apache mod_rewrite (vereist voor Laravel routing)
RUN a2enmod rewrite

# Verander de Apache DocumentRoot naar de Laravel 'public' map
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# Kopieer de applicatie vanuit de eerdere stages
COPY --from=backend /app /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build

# Zorg ervoor dat de opslag- en cachemappen de juiste rechten hebben voor de webserver
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# ==========================================
# Entrypoint (Automatisering)
# ==========================================
# Kopieer het start-script en maak het uitvoerbaar
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose poort 80
EXPOSE 80

# Stel het script in als de opstart-instructie van de container
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
