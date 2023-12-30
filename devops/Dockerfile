FROM php:8.3-alpine

# Set the working directory inside the container
WORKDIR /var/www

# Install Composer
RUN apk --no-cache add curl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install XDebug dependencies
RUN apk --no-cache add $PHPIZE_DEPS linux-headers

# Install XDebug
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Install SVM
RUN pecl install -f svm && docker-php-ext-enable svm

# Install GD & dependencies
RUN apk --no-cache add libpng libpng-dev libjpeg-turbo libjpeg-turbo-dev libwebp libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install gd

# Set the memory limit in php.ini
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/docker-php-memlimit.ini

# Keep the container running
CMD ["tail", "-f", "/dev/null"]