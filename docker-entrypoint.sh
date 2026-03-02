#!/bin/sh
set -e

if [ -z "$(ls -A /var/www/html/src/uploads 2>/dev/null)" ] && \
   [ -d /opt/uploads-seed ]; then
    cp -r /opt/uploads-seed/. /var/www/html/src/uploads/
fi

chown -R www-data:www-data /var/www/html/src/uploads

exec "$@"