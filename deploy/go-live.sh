#!/usr/bin/env bash
# Root-only steps to bring verify.pollus.tech + demos.pollus.tech live (nginx + TLS).
# Everything else (builds, pm2 workers, .env, migrations) is already done.
#
#   sudo bash /var/www/pollus_main_servers/verify.pollus.tech/deploy/go-live.sh
#
set -euo pipefail

DEPLOY=/var/www/pollus_main_servers/verify.pollus.tech/deploy
SA=/etc/nginx/sites-available
SE=/etc/nginx/sites-enabled
STAMP=$(date +%Y%m%d-%H%M%S)

echo ">> Backing up the current (broken) verify vhost, if present"
if [ -f "$SA/verify.pollus.tech" ]; then
  cp -a "$SA/verify.pollus.tech" "$SA/verify.pollus.tech.bak.$STAMP"
fi

echo ">> Installing HTTP-only vhosts (certbot will inject the 443 blocks)"
cp "$DEPLOY/verify.pollus.tech.nginx.conf" "$SA/verify.pollus.tech"
cp "$DEPLOY/demos.pollus.tech.nginx.conf"  "$SA/demos.pollus.tech"

ln -sf "$SA/verify.pollus.tech" "$SE/verify.pollus.tech"
ln -sf "$SA/demos.pollus.tech"  "$SE/demos.pollus.tech"

echo ">> Testing nginx config"
nginx -t

echo ">> Reloading nginx"
systemctl reload nginx

echo ">> Obtaining/installing TLS certificates"
certbot --nginx -d verify.pollus.tech --non-interactive --agree-tos -m innoxitech@gmail.com --redirect
certbot --nginx -d demos.pollus.tech  --non-interactive --agree-tos -m innoxitech@gmail.com --redirect

echo ">> Final nginx test + reload"
nginx -t && systemctl reload nginx

echo
echo ">> Smoke tests"
curl -s -o /dev/null -w "verify /up   -> %{http_code}\n" https://verify.pollus.tech/up   || true
curl -s -o /dev/null -w "demos  /     -> %{http_code}\n" https://demos.pollus.tech/       || true
echo ">> Done."
