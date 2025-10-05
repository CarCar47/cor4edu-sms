# COR4EDU SMS - Google Cloud Run Dockerfile
# PHP 8.3 with Apache for production deployment

FROM php:8.3-apache

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
RUN sed -i 's/80/8080/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Set Apache document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files (selective copy to avoid OneDrive metadata issues)
# CRITICAL: Do NOT use "COPY . /var/www/html/" as it includes OneDrive attributes
COPY composer.json composer.lock* bootstrap.php /var/www/html/
COPY public/ /var/www/html/public/
COPY src/ /var/www/html/src/
COPY modules/ /var/www/html/modules/
COPY resources/ /var/www/html/resources/
COPY database_migrations/ /var/www/html/database_migrations/
COPY config/ /var/www/html/config/

# Install PHP dependencies (production mode)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Create storage directories and set proper permissions
RUN mkdir -p /var/www/html/storage/cache/twig \
    /var/www/html/storage/logs \
    /var/www/html/storage/sessions \
    /var/www/html/storage/uploads \
    /var/www/html/storage/documents \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage

# PHP production configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Copy custom PHP configuration
RUN echo "; COR4EDU SMS PHP Configuration" > /usr/local/etc/php/conf.d/cor4edu.ini && \
    echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/cor4edu.ini && \
    echo "upload_max_filesize = 10M" >> /usr/local/etc/php/conf.d/cor4edu.ini && \
    echo "post_max_size = 10M" >> /usr/local/etc/php/conf.d/cor4edu.ini && \
    echo "max_execution_time = 30" >> /usr/local/etc/php/conf.d/cor4edu.ini && \
    echo "date.timezone = America/New_York" >> /usr/local/etc/php/conf.d/cor4edu.ini && \
    echo "display_errors = Off" >> /usr/local/etc/php/conf.d/cor4edu.ini && \
    echo "log_errors = On" >> /usr/local/etc/php/conf.d/cor4edu.ini && \
    echo "error_log = /var/log/apache2/php_errors.log" >> /usr/local/etc/php/conf.d/cor4edu.ini && \
    echo "" >> /usr/local/etc/php/conf.d/cor4edu.ini && \
    echo "; OPcache settings - DISABLED for Cloud Run to ensure fresh code" >> /usr/local/etc/php/conf.d/cor4edu.ini && \
    echo "opcache.enable=0" >> /usr/local/etc/php/conf.d/cor4edu.ini

# Expose port 8080 (Cloud Run requirement)
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8080/health || exit 1

# Start Apache in foreground
CMD ["apache2-foreground"]
