FROM php:8.2-apache

# Database extensions (MySQL and PostgreSQL)
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_mysql mysqli pdo_pgsql pgsql

# Make .htaccess work, and silence the ServerName warning
# headers  = security headers in .htaccess (was missing, so they were silently skipped)
# expires  = cache lifetimes in .htaccess
RUN a2enmod rewrite headers expires deflate \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Hide the Apache version and OS on error pages and in headers
RUN sed -i 's/^ServerTokens .*/ServerTokens Prod/; s/^ServerSignature .*/ServerSignature Off/' \
    /etc/apache2/conf-available/security.conf

# Block any hidden file or folder (.env, .git, .htaccess, ...) from being downloaded
RUN echo 'RedirectMatch 404 "/\.(?!well-known)"' > /etc/apache2/conf-enabled/z-hide-dotfiles.conf

# PHP hardening: hide the PHP version, and make the session cookie Secure.
# Render handles HTTPS at its proxy, so PHP sees plain HTTP; setting these
# here makes the cookie Secure regardless.
RUN { \
      echo 'expose_php = Off'; \
      echo 'session.cookie_secure = 1'; \
      echo 'session.cookie_httponly = 1'; \
      echo 'session.cookie_samesite = Lax'; \
      echo 'session.use_strict_mode = 1'; \
    } > /usr/local/etc/php/conf.d/zz-security.ini

# Open homepage.php as the main page
RUN echo "DirectoryIndex homepage.php index.php index.html" > /etc/apache2/conf-enabled/z-index.conf

# Listen on port 10000 (Render's default), hardcoded
RUN sed -i 's/^Listen 80$/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:10000>/' /etc/apache2/sites-available/000-default.conf

# Copy your website files (.dockerignore keeps .git and .env out)
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 10000