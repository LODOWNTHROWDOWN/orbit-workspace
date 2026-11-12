#!/bin/sh
set -eu
PORT="${PORT:-8080}"
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/\*:8080/\*:${PORT}/" /etc/apache2/sites-available/000-default.conf
attempt=0
until php /opt/orbit/bin/migrate.php; do
  attempt=$((attempt+1))
  if [ "$attempt" -ge 30 ]; then echo "Database unavailable after retries" >&2; exit 1; fi
  sleep 2
done
exec apache2-foreground
