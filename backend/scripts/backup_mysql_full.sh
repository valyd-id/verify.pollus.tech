#!/usr/bin/env bash
# Full mysqldump via Laravel — credentials from APP .env only.
cd "$(dirname "$0")/.."
mkdir -p storage/app/backups
php artisan database:backup-sql
