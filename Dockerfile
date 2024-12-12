# syntax=docker/dockerfile:1
FROM php:8.0-apache

# Setup the variables for the Apache environment
ENV APACHE_RUN_USER="www-data"
ENV APACHE_RUN_GROUP="www-data"
ENV APACHE_LOG_DIR="/var/log/apache2"
ENV APACHE_RUN_DIR="/var/www/html"

# [Optional] Create a basic `.bashrc` file to customize the bash shell
RUN echo "alias ls='ls --color=auto'" >> ~/.bashrc && \
    echo "alias ll='ls -l'" >> ~/.bashrc && \
    echo "alias la='ls -la'" >> ~/.bashrc && \
    echo "alias l='ls -CF'" >> ~/.bashrc && \
    echo "alias ..='cd ..'" >> ~/.bashrc && \
    echo "PS1='\[\033[01;32m\]\u@\h\[\033[00m\]:\[\033[01;34m\]\w\[\033[00m\]\$ '" >> ~/.bashrc

# Install PHP extensions
RUN apt-get update \
      && apt-get install -y libzip-dev libpng-dev \
      && docker-php-ext-install mysqli \
      && docker-php-ext-install gd \
      && docker-php-ext-install zip;

# Enable Apache modules (mod_rewrite, mod_headers)
RUN a2enmod rewrite \
      && a2enmod headers \
      && service apache2 restart \
      && apachectl restart;

# Install the latest release of Composer
COPY --from=composer/composer:latest-bin /composer /usr/bin/composer

# Specify the port number the container should expose
EXPOSE 80

ENTRYPOINT ["/usr/sbin/apache2", "-D", "FOREGROUND"]