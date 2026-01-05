#!/bin/sh
# Health Check für den Symfony Container

# Prüfe ob Nginx läuft
if ! pgrep -x nginx > /dev/null; then
    echo "Nginx is not running"
    exit 1
fi

# Prüfe ob PHP-FPM läuft
if ! pgrep -x php-fpm > /dev/null; then
    echo "PHP-FPM is not running"
    exit 1
fi

# HTTP Health Check
curl -f http://localhost/health || exit 1

exit 0
