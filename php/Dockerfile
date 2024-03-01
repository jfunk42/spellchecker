FROM php:8.0-apache

RUN apt update \
	&& apt install -y \
            g++ \
            libicu-dev \
            libpq-dev \
            libzip-dev \
            zip \
            zlib1g-dev \
        && docker-php-ext-install \
            intl \
            opcache \
            pdo \
	&& docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable pdo_mysql


WORKDIR /var/bin

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

