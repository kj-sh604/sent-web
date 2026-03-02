FROM php:8.3-apache

# install fonts, fontconfig (for fc-list), and tini (proper PID 1 / signal relay)
RUN apt-get update && apt-get install -y --no-install-recommends \
    tini \
    fontconfig \
    fonts-dejavu \
    fonts-noto-color-emoji \
    fonts-noto-core \
    fonts-liberation \
    fonts-roboto \
    fonts-hack \
    && rm -rf /var/lib/apt/lists/* \
    && fc-cache -fv

# configure apache: set document root to /var/www/html/src
ENV APACHE_DOCUMENT_ROOT=/var/www/html/src
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

# switch apache from port 80 to port 3000
RUN sed -i 's/Listen 80/Listen 3000/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:3000>/' \
       /etc/apache2/sites-available/*.conf

# enable mod_rewrite
RUN a2enmod rewrite

# php upload limits
RUN echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/sent-web.ini \
    && echo "post_max_size = 50M"   >> /usr/local/etc/php/conf.d/sent-web.ini

# copy application
COPY src/ /var/www/html/src/

# ensure uploads directory exists with correct permissions
RUN mkdir -p /var/www/html/src/uploads \
    && chown -R www-data:www-data /var/www/html/src/uploads

EXPOSE 3000

# tini as PID 1 ensures SIGTERM is properly forwarded to apache,
# preventing the 'permission denied' error on docker stop
ENTRYPOINT ["/usr/bin/tini", "--"]
CMD ["apache2-foreground"]