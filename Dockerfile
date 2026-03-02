FROM php:8.3-apache

# enable contrib (fonts-ibm-plex) and non-free (fonts-ubuntu) components
RUN sed -i 's/^Components: main$/Components: main contrib non-free/' /etc/apt/sources.list.d/debian.sources

# install fonts, fontconfig (for fc-list), and tini (proper PID 1 / signal relay)
RUN apt-get update && apt-get install -y --no-install-recommends \
    tini \
    fontconfig \
    fonts-dejavu fonts-dejavu-core fonts-dejavu-extra fonts-dejavu-mono fonts-liberation fonts-liberation2 fonts-opensymbol fonts-urw-base35 fonts-noto-color-emoji fonts-noto-core fonts-noto-ui-core fonts-noto-extra fonts-noto-mono fonts-noto-cjk fonts-noto-cjk-extra fonts-roboto fonts-roboto-slab fonts-lato fonts-open-sans fonts-quicksand fonts-comfortaa fonts-cantarell fonts-beteckna fonts-ubuntu fonts-linuxlibertine fonts-ebgaramond fonts-ebgaramond-extra fonts-junicode fonts-stix fonts-texgyre fonts-sil-gentium fonts-sil-gentium-basic fonts-hack fonts-firacode fonts-cascadia-code fonts-inconsolata fonts-fantasque-sans fonts-terminus fonts-droid-fallback fonts-symbola fonts-ancient-scripts fonts-mathjax fonts-croscore fonts-nanum fonts-nanum-extra fonts-wqy-microhei fonts-wqy-zenhei fonts-arphic-ukai fonts-arphic-uming fonts-ipafont-gothic fonts-ipafont-mincho fonts-indic fonts-lohit-deva fonts-lohit-beng-assamese fonts-lohit-beng-bengali fonts-lohit-gujr fonts-lohit-guru fonts-lohit-knda fonts-lohit-mlym fonts-lohit-orya fonts-lohit-taml fonts-lohit-taml-classical fonts-lohit-telu fonts-smc fonts-arabeyes fonts-hosny-amiri fonts-sil-abyssinica fonts-beng fonts-thai-tlwg fonts-gfs-artemisia fonts-gfs-baskerville fonts-gfs-bodoni-classic fonts-gfs-didot fonts-gfs-gazis fonts-gfs-neohellenic fonts-gfs-olga fonts-gfs-porson fonts-gfs-solomos fonts-gfs-theokritos fonts-crosextra-carlito fonts-crosextra-caladea fonts-cabin fonts-vollkorn fonts-yanone-kaffeesatz fonts-ibm-plex fonts-freefont-ttf fonts-mplus fonts-monofur fonts-courier-prime fonts-anonymous-pro fonts-hermit

# install Roboto Mono manually (not packaged in Debian, kj_sh604's fave font)
RUN apt-get update && apt-get install -y --no-install-recommends curl \
    && mkdir -p /usr/local/share/fonts/roboto-mono \
    && curl -fsSL "https://cdn.jsdelivr.net/gh/googlefonts/RobotoMono@main/fonts/ttf/RobotoMono-Regular.ttf" \
         -o "/usr/local/share/fonts/roboto-mono/RobotoMono-Regular.ttf" \
    && fc-cache -fv \
    && rm -rf /var/lib/apt/lists/*

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

# copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# copy application
COPY src/ /var/www/html/src/

# stash a seed copy of uploads so the entrypoint can populate a fresh volume
RUN mkdir -p /opt/uploads-seed \
    && cp -r /var/www/html/src/uploads/. /opt/uploads-seed/ \
    && chown -R www-data:www-data /var/www/html/src/uploads /opt/uploads-seed

EXPOSE 3000

# tini as PID 1 ensures SIGTERM is properly forwarded to apache,
# preventing the 'permission denied' error on docker stop
ENTRYPOINT ["/usr/bin/tini", "--", "/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]