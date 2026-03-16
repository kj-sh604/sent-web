#!/bin/sh
set -e

if [ -z "$(ls -A /app/uploads 2>/dev/null)" ] && \
   [ -d /opt/uploads-seed ]; then
    cp -r /opt/uploads-seed/. /app/uploads/
fi

chown -R www-data:www-data /app/uploads

exec "$@"