# Deploying TrinetPay

Written for one Ubuntu 22.04 or 24.04 server that runs everything: the web app, MySQL, the queue worker and
FreeRADIUS. It also works with FreeRADIUS on its own server, as long as it can reach MySQL.

## 0. Before you start

- A domain (`trinetpay.online`) and a **wildcard DNS record** `*.trinetpay.online` pointing at the server, so
  every ISP subdomain works.
- A **wildcard HTTPS certificate** for `trinetpay.online` and `*.trinetpay.online`. A wildcard certificate can
  only be issued with a DNS challenge, for example `certbot` with your DNS provider's plugin.
- A **public IPv4 address** on that server for RADIUS (UDP 1812 and 1813).
- PalmPesa merchant credentials.

Ports to open: 80 and 443 (web), UDP 1812 and 1813 (RADIUS). Keep MySQL closed to the internet.

## 1. Install

```bash
sudo apt update
sudo apt install nginx mysql-server php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
     php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl unzip git supervisor certbot
# Composer: https://getcomposer.org/download/
sudo timedatectl set-timezone UTC        # RADIUS expiry dates are in UTC
```

## 2. Get the code and set it up

```bash
sudo mkdir -p /var/www/trinetpay && sudo chown $USER /var/www/trinetpay
git clone <your repository> /var/www/trinetpay
cd /var/www/trinetpay
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Edit `.env`. These must be set, the file explains each one:

| Setting | Value |
|---|---|
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | `production`, `false`, `https://trinetpay.online` |
| `DB_*` | the MySQL database and user (create them first) |
| `SESSION_SECURE_COOKIE` | `true` |
| `QUEUE_CONNECTION` | `database` |
| `PALMPESA_BASE_URL`, `PALMPESA_API_KEY`, `PALMPESA_USER_ID` | from PalmPesa |
| `RADIUS_HOST`, `RADIUS_SECRET` | the server's public IP, and a long random secret (`openssl rand -base64 24`) |
| `PLATFORM_WITHDRAWAL_FEE_PCT` | the fee taken from withdrawals |
| `TRUSTED_PROXIES` | the proxy address if one sits in front of nginx, otherwise leave empty |
| `HEALTH_TOKEN` | a long random value for your uptime monitor |
| `SMS_DRIVER` | `log` until you have a Beem account, then `beem` with the keys |
| `MAIL_*` | a real mail server, so router alerts reach owners |

```bash
php artisan migrate --force
php artisan storage:link
php artisan admin:create you@yourdomain.com          # shows a strong password once, store it
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

## 3. Web server

Copy [`nginx/trinetpay.conf`](nginx/trinetpay.conf) to `/etc/nginx/sites-available/`, fix the certificate
paths, link it into `sites-enabled`, then `sudo nginx -t && sudo systemctl reload nginx`.

## 4. Background jobs

Two things must always be running, or payments and alerts stop working quietly.

```bash
# The queue worker (grants access, sends SMS receipts)
sudo cp deploy/supervisor/trinetpay-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start trinetpay-worker:*

# The scheduler (reconciles payments, checks routers, cleans up), every minute
( sudo crontab -u www-data -l 2>/dev/null; echo '* * * * * cd /var/www/trinetpay && php artisan schedule:run >> /dev/null 2>&1' ) | sudo crontab -u www-data -
```

After a deployment, restart the worker so it picks up new code: `php artisan queue:restart`.

## 5. RADIUS

Follow [`freeradius/README.md`](freeradius/README.md). Do this on a staging server first, and test with a real
router before real customers use it.

## 6. Backups

```bash
sudo cp deploy/backup.sh /usr/local/bin/trinetpay-backup && sudo chmod +x /usr/local/bin/trinetpay-backup
# edit the settings at the top of the script, then run it daily at 02:00:
echo '0 2 * * * root /usr/local/bin/trinetpay-backup' | sudo tee /etc/cron.d/trinetpay-backup
```

A backup you have never restored is a guess. Do the restore drill in [`../docs/RUNBOOK.md`](../docs/RUNBOOK.md)
once before launch, and keep a copy of the backups on another machine.

## 7. Monitoring

Point an uptime monitor at `https://trinetpay.online/health`. It returns `ok`, `degraded` (something needs
attention, the site still works) or `down` (HTTP 503). Send the `X-Health-Token` header to see the details:
database, scheduler heartbeat, queue backlog, payments stuck pending, customers who paid without access.

## Go-live checklist

- [ ] `.env` has `APP_DEBUG=false` and a real `APP_KEY` that is backed up somewhere safe. Losing it makes
      stored router passwords unreadable.
- [ ] HTTPS works for `trinetpay.online` and a test subdomain. Headers show `Strict-Transport-Security`.
- [ ] `php artisan schedule:list` shows the jobs, and `/health` says `ok` after a minute.
- [ ] The queue worker is running: `sudo supervisorctl status`.
- [ ] A test payment of the smallest amount settles, credits a wallet, and shows in `wallet:audit` as OK.
- [ ] A withdrawal request, approval and payout work, and the fee is taken.
- [ ] FreeRADIUS accepts a real customer login and rejects the wrong ISP, an expired login and a suspended ISP.
- [ ] A router on RouterOS v6 and one on v7 both complete the setup command and show as online.
- [ ] A backup ran and was restored to a scratch database.
- [ ] `TRUSTED_PROXIES` is set correctly if there is a proxy: the sign-in limit must count visitors, not the proxy.
- [ ] You know who gets the alert emails, and `MAIL_*` is real.
- [ ] Two-factor sign-in for the platform admin is planned. It is not built yet.

## Updating

```bash
cd /var/www/trinetpay
php artisan wallet:audit                     # before: note any flagged wallet
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan wallet:audit                     # after: nothing new should be flagged
```

Migrations only add things. Take a backup first anyway.

## Load testing

[`loadtest/portal.k6.js`](loadtest/portal.k6.js) opens a portal and polls a payment status the way customers
do. Run it against staging with [k6](https://k6.io) and watch `/health` and the database while it runs.
It has not been run yet.
