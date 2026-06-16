# Deploying `verify.pollus.tech` (staging) → `verify.valyd.id` (production)

The service code lives at `/var/www/pollus_main_servers/verify.pollus.tech/`
(directory name retained). Staging domain is **verify.pollus.tech**; production
will be **verify.valyd.id** (swap `server_name` + the certbot `-d` flag). It is a
standard Laravel app served by **nginx + php8.4-fpm**, with a **supervisor**
queue worker for webhooks and a scheduler for session expiry.

## 1. DNS
Add an `A` record `verify.pollus.tech` → `96.250.208.62` (same public IP as the
other `*.pollus.tech` services). Confirm with `getent hosts verify.pollus.tech`.

## 2. App config
`backend/.env` is already set for production:
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` / `VERIFY_HOSTED_BASE_URL=https://verify.valyd.tech`
- Postgres `verify_valyd`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`
- Engines: `REMOTE_FACE_URL`, `REMOTE_FACE_OCR_URL`, `ZK_VERIFY_API_URL=https://zkv.pollus.us/prove`,
  `VC_API_KEY` (vc.pollus.tech bulk key)

```bash
cd /var/www/pollus_main_servers/verify.pollus.tech/backend
php artisan migrate --force
php artisan config:cache && php artisan route:cache
# writable dirs for the web user
sudo chown -R www-data:www-data storage bootstrap/cache
```

## 3. nginx + TLS
```bash
sudo cp ../deploy/verify.pollus.tech.nginx.conf /etc/nginx/sites-available/verify.pollus.tech
sudo ln -s /etc/nginx/sites-available/verify.pollus.tech /etc/nginx/sites-enabled/
sudo certbot --nginx -d verify.pollus.tech
sudo nginx -t && sudo systemctl reload nginx
```

## 4. Queue worker + scheduler (supervisor)
```bash
sudo cp ../deploy/verify-valyd-worker.conf    /etc/supervisor/conf.d/
sudo cp ../deploy/verify-valyd-scheduler.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start verify-valyd-worker:* verify-valyd-scheduler
```

## 5. Provision a client
```bash
php artisan verify:create-project "Acme KYC" --webhook=https://acme.example/webhooks/valyd
# prints api_key + webhook_signing_secret (shown once)
```

## 6. Smoke test
```bash
curl https://verify.pollus.tech/up                      # 200
curl -X POST https://verify.pollus.tech/api/v2/workflows \
  -H "X-API-Key: <key>" -H 'Content-Type: application/json' \
  -d '{"name":"Full KYC","features":["id_verification","liveness","face_match"]}'
```

## Going to production (verify.valyd.id)
Repeat steps 3–4 with `verify.valyd.id`: edit `server_name` in the nginx conf,
`sudo certbot --nginx -d verify.valyd.id`, and set `APP_URL` /
`VERIFY_HOSTED_BASE_URL=https://verify.valyd.id` in `.env`, then
`php artisan config:cache`.

## Notes / known external dependencies
- **Age (ZKP)**: `zkv.pollus.us` currently returns `402 INSUFFICIENT_BALANCE`
  (the shared zkVerify testnet account is out of tVFY). Age checks pass once that
  account is funded — integration itself is correct.
- **Credential**: uses the `vc.pollus.tech` bulk endpoint with `VC_API_KEY`.
  Swap `test-api-key-12345` for a production Company key when available.
- After changing `.env`, re-run `php artisan config:cache`.
