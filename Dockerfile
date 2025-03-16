FROM php

RUN docker-php-ext-install -j$(nproc) pdo_mysql