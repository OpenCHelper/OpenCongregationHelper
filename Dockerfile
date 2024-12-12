# syntax=docker/dockerfile:1
FROM php:8.2-apache

# Setup the variables for the Apache environment
ENV APACHE_RUN_USER="www-data"
ENV APACHE_RUN_UID=1000
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
      && docker-php-ext-install mysqli pdo pdo_mysql \
      && docker-php-ext-install gd \
      && docker-php-ext-install zip;

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache modules (mod_rewrite, mod_headers)
RUN a2enmod rewrite \
      && a2enmod headers \
      && service apache2 restart \
      && apachectl restart;

# Install the latest release of Composer
COPY --from=composer/composer:latest-bin /composer /usr/bin/composer

# Create system user to run Composer and Artisan Commands
RUN mkdir -p /home/$APACHE_RUN_USER/.composer && \
    chown -R $APACHE_RUN_USER /home/$APACHE_RUN_USER

# Set working directory
WORKDIR /var/www/html

USER $user

# Specify the port number the container should expose
EXPOSE 80

CMD ["/usr/sbin/apache2", "-D", "FOREGROUND"]