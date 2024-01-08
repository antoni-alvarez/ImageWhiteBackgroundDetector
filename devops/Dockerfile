FROM php:8.1-cli-alpine

# Set the working directory inside the container
WORKDIR /var/www

# Install Composer
RUN apk --no-cache add curl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install XDebug extension & dependencies
RUN apk --no-cache add $PHPIZE_DEPS linux-headers \
    && pecl install xdebug && docker-php-ext-enable xdebug

# Install Tensor extension & dependencies
RUN apk --no-cache add openblas-dev \
    && pecl install tensor && docker-php-ext-enable tensor

# Install SVM extension
RUN pecl install -f svm && docker-php-ext-enable svm

# Install GD extension & dependencies
RUN apk --no-cache add libpng libpng-dev libjpeg-turbo libjpeg-turbo-dev libwebp libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install gd

# Set the memory limit in php.ini
RUN echo "memory_limit=1024M" > /usr/local/etc/php/conf.d/docker-php-memlimit.ini

# Keep the container running
CMD ["tail", "-f", "/dev/null"]