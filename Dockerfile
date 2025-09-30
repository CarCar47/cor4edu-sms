# COR4EDU SMS - Google Cloud Run Dockerfile
# PHP 8.1 with Apache for production deployment

FROM php:8.1-apache

LABEL maintainer="COR4EDU Development Team <dev@cor4edu.com>"
LABEL description="COR4EDU Student Management System"
LABEL version="1.0.0"

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mysqli \
        zip \
        gd \
        mbstring \
        xml \
        opcache

# Enable Apache modules
RUN a2enmod rewrite headers

# Configure Apache for Cloud Run
RUN sed -i 's/80/8080/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Set Apache document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . /var/www/html/

# Install PHP dependencies (production mode)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage

# PHP production configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Copy custom PHP configuration
COPY <<EOF /usr/local/etc/php/conf.d/cor4edu.ini
; COR4EDU SMS PHP Configuration
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 30
date.timezone = America/New_York
display_errors = Off
log_errors = On
error_log = /var/log/apache2/php_errors.log

; OPcache settings for performance
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
EOF

# Expose port 8080 (Cloud Run requirement)
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8080/health || exit 1

# Start Apache in foreground
CMD ["apache2-foreground"]
