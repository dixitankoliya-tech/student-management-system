FROM php:8.2-apache

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql opcache

# Enable Apache mod_rewrite for nice URLs if needed
RUN a2enmod rewrite

# Update Apache configuration to use dynamic port provided by Render
# Render provides the PORT environment variable
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Copy application files to the Apache document root
COPY . /var/www/html/

# Ensure the uploads directory exists and has proper permissions
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads
