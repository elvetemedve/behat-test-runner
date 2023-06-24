#!/usr/bin/env sh

echo "Test worked.."
php vendor/bin/phpunit tests
php vendor/bin/phpcs --standard=PSR12 -q --report=checkstyle /var/www/html/src
