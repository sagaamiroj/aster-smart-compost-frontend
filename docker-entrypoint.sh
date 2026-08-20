#!/bin/bash

set -e

# Railway menyediakan PORT melalui environment variable.
# Default 80 jika PORT tidak tersedia.
PORT=${PORT:-80}

# Ubah konfigurasi Apache agar listen pada PORT Railway.
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf

# Ubah VirtualHost Apache agar menggunakan PORT yang sama.
sed -i "s/<VirtualHost \*:[0-9]\+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Jalankan Apache sebagai foreground process.
exec apache2-foreground
