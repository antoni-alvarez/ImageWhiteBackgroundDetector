FROM php:8.3-alpine

# Set the working directory inside the container
WORKDIR /var/www

# Install Composer
RUN apk --no-cache add curl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install GD & dependencies
RUN apk --no-cache add libpng libpng-dev libjpeg-turbo libjpeg-turbo-dev libwebp libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install gd

# Set the memory limit in php.ini
RUN echo "memory_limit=256M" > /usr/local/etc/php/conf.d/docker-php-memlimit.ini

# Expose port 80 for web server
EXPOSE 80

# Start the PHP built-in server
CMD ["php", "-S", "0.0.0.0:80", "-t", "/var/www/public"]