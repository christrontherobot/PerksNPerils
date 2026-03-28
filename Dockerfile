# Use an official PHP image with Apache
FROM php:8.2-apache

# Install PostgreSQL client and PDO drivers
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Enable Apache mod_rewrite (useful for clean URLs later)
RUN a2enmod rewrite

# Copy your project files into the web server directory
COPY . /var/www/html/

# Set the Document Root to the /public folder
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Use the PORT environment variable provided by Render
EXPOSE 10000