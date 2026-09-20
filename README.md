# TrinetPay

A hotspot billing platform for small internet providers in Tanzania and East Africa.

An ISP signs up, connects their MikroTik router with one pasted command, and immediately has a branded
payment portal. Customers pay with mobile money, or redeem a printed voucher, and are online in seconds.
The platform is free to use. The only charge is a small fee taken when an ISP withdraws its earnings.

## How it works

```
customer phone -> MikroTik hotspot -> tenant portal (acme.wifikitaa.site) -> pays with mobile money (PalmPesa)
                       |                                                              |
                       | RADIUS login (router connects OUT)                           | confirmed payment
                       v                                                              v
                  FreeRADIUS  <-------------- reads the customer login --------  Laravel (this app)
```

- **Any RouterOS version.** Routers connect out to the platform, so nothing has to be opened on the router,
  and it works behind another router or a mobile connection.
- **Money is safe.** Payments are confirmed with the gateway, settled once, and written to an append-only
  ledger. Voucher and agent sales are cash the ISP already holds and never enter the withdrawable wallet.
- **Multi-tenant.** Every ISP sees only its own data, enforced in the models and checked by tests.

## Documents

| Read | For |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | How the pieces fit, the money rules, what is and is not verified |
| [deploy/DEPLOY.md](deploy/DEPLOY.md) | Putting it on a server, step by step, with a go-live checklist |
| [deploy/freeradius/README.md](deploy/freeradius/README.md) | The RADIUS server that logs customers in |
| [docs/RUNBOOK.md](docs/RUNBOOK.md) | What to do when something goes wrong, and routine jobs |

## Run it locally

Needs PHP 8.3 or newer, Composer, and MySQL (or SQLite for a quick look).

```bash
composer install
cp .env.example .env
php artisan key:generate
# set DB_* in .env, then:
php artisan migrate
php artisan admin:create you@example.com     # prints a strong password once
php artisan serve
```

Every ISP has their own portal address, `/portal/<their key>`, for example
`http://localhost:8000/portal/acme`. The key is the ISP's subdomain slug. A link that names no ISP sells
nothing, because a payment with no ISP behind it has no packages, no wallet to credit and no router to open.

Run the background pieces in separate terminals:

```bash
php artisan queue:work        # settles access grants and sends SMS receipts
php artisan schedule:work     # reconciles payments, checks routers, cleans up
```

## Tests

```bash
composer install
vendor/bin/phpunit
```

The suite is self-contained: it uses an in-memory database, its own encryption key, and fakes every outside
call (PalmPesa, the SMS provider, routers). It never needs real credentials and cannot reach the real services.

## Useful commands

| Command | What it does |
|---|---|
| `php artisan wallet:audit` | Lists any ISP wallet that real payments cannot explain. Run before paying withdrawals. |
| `php artisan payments:reconcile` | Asks the gateway about payments still pending. Runs every minute on its own. |
| `php artisan router:heartbeat` | Marks routers online or offline and sends alerts. Runs every five minutes. |
| `php artisan admin:create <email>` | Creates a platform administrator. |
| `php artisan data:prune` | Removes old bulk data. Runs daily. Never touches money or the audit trail. |

## Settings that matter

Everything is in `.env.example` with comments. The important ones are the PalmPesa keys, `RADIUS_HOST` and
`RADIUS_SECRET`, `PLATFORM_WITHDRAWAL_FEE_PCT`, `SMS_DRIVER`, and for production `TRUSTED_PROXIES`.
