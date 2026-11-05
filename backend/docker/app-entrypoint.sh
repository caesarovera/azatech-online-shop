#!/bin/sh
# Wrapper entrypoint:
# 1. Jika APP_KEY belum di-set (mis. menjalankan "docker compose up" tanpa .env), buat key sementara
#    supaya stack tetap bisa jalan. API ini stateless (Bearer token, tanpa session/cookie terenkripsi),
#    jadi key per-container tidak mempengaruhi fungsi. Untuk production WAJIB set APP_KEY sendiri.
# 2. Cache config/route/event untuk performa, lalu serahkan ke entrypoint bawaan image.
set -e

if [ -z "${APP_KEY}" ]; then
    APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
    export APP_KEY
    echo "⚠️  APP_KEY tidak di-set — memakai key sementara. Set APP_KEY di .env untuk production." >&2
fi

php /var/www/html/artisan optimize --quiet || php /var/www/html/artisan optimize:clear --quiet

exec docker-php-serversideup-entrypoint "$@"
