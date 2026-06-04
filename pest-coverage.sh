#!/bin/bash
php -d memory_limit=1G \
    -d pcov.enabled=1 \
    ./vendor/bin/pest --coverage --min=80
