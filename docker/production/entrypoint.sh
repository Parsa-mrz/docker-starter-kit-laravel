#!/bin/bash
php artisan migrate
php artisan config:cache
php artisan route:cache
supervisord -c /etc/supervisor/supervisord.conf