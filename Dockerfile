FROM php:8.2-apache

# Database extensions (MySQL and PostgreSQL)
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_mysql mysqli pdo_pgsql pgsql

# Make .htaccess work, and silence the ServerName warning
RUN a2enmod rewrite \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Open homepage.php as the main page
RUN echo "DirectoryIndex homepage.php index.php index.html" > /etc/apache2/conf-enabled/z-index.conf

# Listen on port 10000 (Render's default), hardcoded
RUN sed -i 's/^Listen 80$/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:10000>/' /etc/apache2/sites-available/000-default.conf

# Copy your website files
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 10000