FROM php:8.3-apache

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY index.html /var/www/html/index.html

RUN chown -R www-data:www-data /var/www/html

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
