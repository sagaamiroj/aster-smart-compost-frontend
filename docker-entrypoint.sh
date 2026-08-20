#!/bin/bash

set -e

PORT=${PORT:-80}

# Railway dynamic port
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf

sed -i "s/<VirtualHost \*:[0-9]\+>/<VirtualHost *:${PORT}>/" \
    /etc/apache2/sites-available/000-default.conf

# Pastikan hanya satu Apache MPM yang aktif
a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

exec apache2-foreground
