#!/usr/bin/env sh

php vendor/bin/phpunit tests/
php vendor/bin/ecs check src/
php vendor/bin/ecs check tests/
php vendor/bin/phpstan analyse src --level=8
php vendor/bin/phpstan analyse tests --level=5
