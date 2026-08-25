# UTP Vendor Code Requisition — application image
# PHP 8.3 + Apache with every system + PHP dependency the app needs baked in,
# so nobody has to install PHP, Composer, Tesseract or any extension locally.
FROM php:8.4-apache

# --- System packages -------------------------------------------------------
# - build/dev libs: required to compile the PHP extensions below
# - tesseract-ocr (+ eng data): required by thiagoalessio/tesseract_ocr
# - git/unzip: required by Composer to fetch & extract packages
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        libonig-dev \
        libxml2-dev \
        tesseract-ocr \
        tesseract-ocr-eng \
    && rm -rf /var/lib/apt/lists/*

# --- PHP extensions --------------------------------------------------------
# mysqli   -> database access (config.php)
# gd       -> dompdf image rendering
# zip      -> dompdf / composer archives
# mbstring, dom, xml -> dompdf + pdfparser (mbstring/dom bundled, enabled here)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        mysqli \
        gd \
        zip \
        mbstring \
        dom \
        xml

# --- Composer --------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Apache ----------------------------------------------------------------
RUN a2enmod rewrite \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Larger upload limits for the document/PDF upload flows
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

# Install PHP dependencies first (better layer caching on code changes)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# Copy the rest of the application
COPY . .

# Apache needs to own the tree so document uploads can be written
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
