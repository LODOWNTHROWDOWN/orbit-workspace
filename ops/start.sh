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

# Ensure only Apache's prefork MPM is enabled
a2dismod -f mpm_event mpm_worker 2>/dev/null || true
rm -f /etc/apache2/mods-enabled/mpm_event.* \
      /etc/apache2/mods-enabled/mpm_worker.*
a2enmod mpm_prefork

# Verify Apache configuration before starting
apache2ctl configtest

exec apache2-foreground

