FROM php:8.2-apache

# Install and enable mysqli extension
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Configure PHP upload and execution limits
RUN echo "upload_max_filesize = 15M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 15M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini

# Copy application source code to Apache directory
COPY . /var/www/html/

# Create uploads directory and set permissions for Apache user
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/ \
    && chmod -R 755 /var/www/html/

# Enable Apache Rewrite Module
RUN a2enmod rewrite

EXPOSE 80
