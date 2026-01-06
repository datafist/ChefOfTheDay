#!/bin/sh
# Health Check für den Symfony Container

# Prüfe ob Nginx läuft (Alpine-kompatibel)
if ! pidof nginx > /dev/null; then
    echo "Nginx is not running"
    exit 1
fi

# Prüfe ob PHP-FPM läuft
if ! pidof php-fpm > /dev/null; then
    echo "PHP-FPM is not running"
    exit 1
fi

# HTTP Health Check
wget -q -O /dev/null http://localhost/health || exit 1

exit 0
