# Runbook

What to do when something goes wrong, and the jobs to do regularly. Written to be followed at 2 a.m.

The quick check for everything: open `https://trinetpay.online/health` with the `X-Health-Token` header. It tells
you which part is unhappy: database, scheduler, queue, payments.

```bash
curl -s -H "X-Health-Token: $HEALTH_TOKEN" https://trinetpay.online/health
```

## Someone says "I paid but I am not online"

1. Ask for the **reference** shown on the portal, or the phone number. In the ISP dashboard open
   *Transactions* and search the reference. As platform admin use *Audit trail* and the ISP page.
2. Read the row:

| Status | Access | What it means | What to do |
|---|---|---|---|
| Pending | | The gateway has not confirmed it. | Wait a minute, the reconciler retries. If the customer really paid, run `php artisan payments:reconcile`. If still pending, check with PalmPesa using the order id. |
| Failed | | The gateway said the payment did not go through. | If PalmPesa shows it as paid, run `payments:reconcile`. A late confirmation is still settled. |
| Paid | Online | Money and login are fine. | The problem is on the router or the customer's phone. See *Customers cannot log in* below. |
| Paid | Needs help | Money is fine, the router has not accepted the login (routers connected by API only). | Check the router is online, then `php artisan queue:retry all`. |

## Customers cannot log in on one router

Most likely first:

1. **The router was renamed.** Every login only works on routers named `nas-<id>-...`. The ISP dashboard shows
   "Customers cannot log in on ..." when the agent sees a different name. Fix: `/system identity set name=<the nas
   name>` on the router, or paste the setup command again.
2. **The router is offline** or cannot reach the RADIUS server. Check its internet and that UDP 1812/1813 to the
   server are not blocked by the site's firewall.
3. **The hotspot profile lost its RADIUS setting** (someone reset it). Paste the setup command again.
4. **The customer's time ran out.** Logins expire exactly at the end of the package.

## Nobody can log in anywhere (RADIUS problem)

Payments still work, customers just cannot connect.

```bash
sudo systemctl status freeradius
sudo journalctl -u freeradius -n 100 --no-pager
sudo systemctl stop freeradius && sudo freeradius -X     # debug run, shows every request
```

Common causes: MySQL password changed (update `mods-available/sql`), the MySQL user lost its grants (rerun
`grants.sql`), the server clock is not UTC (every login looks expired or never expires), the disk is full.

## Router offline alert

The owner is emailed and texted after about ten minutes of silence, once per outage. Usual causes: power cut,
the site's internet is down, someone unplugged it. The platform cannot fix these. If many routers go offline at
the same time, the problem is on our side: check `/health` and the web server.

## Payments are stuck or the site is degraded

`/health` says `degraded`:

- **payments: stuck_pending.** Paid orders whose confirmation did not come in. Check the scheduler is running
  (`sudo crontab -u www-data -l`, and `/health` scheduler check), then `php artisan payments:reconcile`. If PalmPesa
  itself is down, payments settle by themselves once it is back. Customers see "still waiting" with a reference.
- **queue.** `sudo supervisorctl status`. If the worker is stopped, `sudo supervisorctl start trinetpay-worker:*`.
  `php artisan queue:failed` lists jobs that gave up, `php artisan queue:retry all` runs them again.
- **payments: paid_without_access.** Customers paid and the router did not accept the login (API-mode routers).
  Fix the router, then `php artisan queue:retry all`.

`/health` says `down`:

- **scheduler.** The cron job is missing or failing. Run `php artisan schedule:run` by hand and read the error.
- **database.** Check MySQL, disk space, and the `.env` credentials.

## Withdrawals: the procedure

Never pay a withdrawal without this check.

1. Open **Reconciliation** in the admin panel. It must say every wallet is explained, and *Platform's own money*
   must not be negative. If it is red, stop and investigate first (`php artisan wallet:audit` shows the same).
2. Open the withdrawal. If it shows *Different from saved number*, phone the ISP owner on a number you already
   had before approving. That is the sign of a hijacked account.
3. Approve, send the **Send** amount (the net, after the fee) from PalmPesa, then mark it paid. The fee is
   recorded when you mark it paid.
4. Compare the *Expected PalmPesa balance* on the Reconciliation page with the real balance now and then. The
   difference should only be PalmPesa's own charges.

## Suspected fraud or a hijacked account

1. Suspend the ISP (admin, ISP page). This blocks every customer login of that ISP at once and disconnects
   customers within a minute. Their money is untouched.
2. Read the **Audit trail** for that ISP: sign-ins, payout number changes, withdrawals, router commands.
3. Reject any pending withdrawal (the full amount returns to the wallet) or pay it once you are sure.
4. To help the owner, use *Open as ISP*. It is recorded, and withdrawals are blocked while you are in there.
5. Make the owner change their password. Activate the ISP when safe.

## Backups: restore drill

Do this once before launch and again every few months. It proves the backup works.

```bash
# 1. Pick the newest backup and check it
ls -lt /var/backups/trinetpay/daily | head -3
gzip -t /var/backups/trinetpay/daily/<file>.sql.gz

# 2. Restore into a scratch database, never over the live one
mysql -e "CREATE DATABASE restore_test"
gunzip -c /var/backups/trinetpay/daily/<file>.sql.gz | mysql restore_test

# 3. Check it holds real data
mysql restore_test -e "SELECT COUNT(*) FROM transactions; SELECT COUNT(*) FROM tenants; SELECT SUM(balance) FROM tenant_wallets;"

# 4. Run the wallet checks against the copy: point a copy of .env at restore_test, then
php artisan wallet:audit

# 5. Clean up
mysql -e "DROP DATABASE restore_test"
```

To recover for real: put the site in maintenance (`php artisan down`), restore into the live database, run
`php artisan migrate --force`, `php artisan wallet:audit`, then `php artisan up`.

## Changing secrets

| Secret | How |
|---|---|
| A router's setup and agent secrets | ISP dashboard, router page, *Replace secrets*, then paste the new command. Until then the router shows offline. |
| `RADIUS_SECRET` | Change it in `.env` and in FreeRADIUS `clients.conf`, restart FreeRADIUS, then every router must run its setup command again. Plan this as a maintenance window. |
| A platform admin password | Create a new admin with `php artisan admin:create`, sign in, then remove the old one in the database. |
| `APP_KEY` | Do not change it. Router passwords for API-mode routers are encrypted with it and would become unreadable. Keep it backed up. |
| PalmPesa keys | Change in `.env`, then `php artisan config:cache`. |

## Routine jobs

**Every day:** open the admin *Reconciliation* page. Look at `/health`.

**Every week:** read the Audit trail for surprises. Check that the last backup exists and is not tiny.

**Every month:** restore drill. Look at how many routers are offline for long and ask those ISPs why. Check disk space
(the RADIUS accounting table grows), and that `data:prune` ran.

**Before any release:** `php artisan wallet:audit`, take a backup, deploy, `php artisan queue:restart`,
`php artisan wallet:audit` again.
