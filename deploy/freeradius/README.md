# FreeRADIUS server for TrinetPay

> **Status: written but not tested against a live FreeRADIUS.** This project was built on a
> Windows machine without Docker or WSL. The Laravel side is covered by automated tests, the
> files in this folder are not. Follow the checks at the end of this page on a staging server
> before pointing any real router at it.

FreeRADIUS answers the login requests that every tenant router sends. It reads the same MySQL
database as the Laravel app. The app writes a customer login (`radcheck` and `radreply` rows)
when a payment or voucher succeeds, and FreeRADIUS reads it when the router asks.

```
customer phone -> MikroTik router --(RADIUS UDP 1812/1813, router connects OUT)--> FreeRADIUS -> MySQL <- Laravel
```

Nothing has to be opened on any tenant router, and it works on every RouterOS version.

## 1. Server

Ubuntu 22.04 or 24.04 with a **public IPv4 address**. Set that address as `RADIUS_HOST` in the Laravel
`.env` (RouterOS wants an IP address here, not a host name).

Only these ports need to be reachable from the internet:

| Port | Use |
|---|---|
| UDP 1812 | RADIUS login |
| UDP 1813 | RADIUS accounting (usage reports) |

Keep MySQL closed to the internet. If FreeRADIUS runs on the same machine as MySQL, it connects to
`127.0.0.1`.

**Set the server clock to UTC.** Laravel writes every `Expiration` date in UTC, and FreeRADIUS reads it in
the server's local time.

```bash
sudo timedatectl set-timezone UTC
```

## 2. Install

```bash
sudo apt update
sudo apt install freeradius freeradius-mysql
sudo systemctl stop freeradius
```

## 3. Database user

FreeRADIUS gets its own MySQL user that can only touch the RADIUS tables. Edit the password in
[`grants.sql`](grants.sql), then run it as the MySQL root user:

```bash
sudo mysql < deploy/freeradius/grants.sql
```

The tables themselves are created by the Laravel migrations (`php artisan migrate`), not by FreeRADIUS.

## 4. Configure

```bash
# The SQL module: copy the file, then put the real password in it.
sudo cp deploy/freeradius/sql.conf /etc/freeradius/3.0/mods-available/sql
sudo ln -sf ../mods-available/sql /etc/freeradius/3.0/mods-enabled/sql

# Who may talk to the server. Put the value of RADIUS_SECRET from the Laravel .env in the secret line.
sudo cp deploy/freeradius/clients.conf /etc/freeradius/3.0/clients.conf

sudo chown -R freerad:freerad /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/clients.conf
sudo chmod 640 /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/clients.conf
```

Adjust `3.0` in those paths if your package installed a different version folder (`ls /etc/freeradius`).

The stock `sites-available/default` already calls `-sql` in the authorize, accounting, session and
post-auth sections, and already runs `expiration` in authorize. Once the SQL module is enabled nothing else
has to change. Confirm it:

```bash
grep -nE "^\s*-?sql|^\s*expiration" /etc/freeradius/3.0/sites-enabled/default
```

You should see `expiration` and `-sql` (or `sql`) lines in `authorize`, `accounting`, `session` and `post-auth`.

## 5. Start it in debug mode first

```bash
sudo freeradius -X
```

Read the start-up output. It must end with `Ready to process requests`. Leave it running and, from the same
machine, test with a login the Laravel app created (find one with
`SELECT username FROM radcheck WHERE attribute='Cleartext-Password' LIMIT 1;`):

```bash
radtest TNABC12345 TNABC12345 127.0.0.1 0 <RADIUS_SECRET>
```

A plain `radtest` does not send a router identity, and every login is tied to the tenant's routers by
`NAS-Identifier`, so a real login test needs that attribute. Use `radclient`:

```bash
printf 'User-Name = "TNABC12345"\nUser-Password = "TNABC12345"\nNAS-Identifier = "nas-1-abc12345"\n' \
  | radclient -x 127.0.0.1:1812 auth <RADIUS_SECRET>
```

Expected results:

| Test | Expected |
|---|---|
| Correct login, `NAS-Identifier` of the same tenant (`nas-<tenant id>-...`) | `Access-Accept` with `Mikrotik-Rate-Limit` and `Session-Timeout` |
| Same login, `NAS-Identifier` of another tenant | `Access-Reject` |
| Login whose `Expiration` date has passed | `Access-Reject` |
| Wrong password | `Access-Reject` |
| Login of a suspended tenant (rows with `Auth-Type := Reject`) | `Access-Reject` |

When all five behave, stop the debug run and start the service:

```bash
sudo systemctl enable --now freeradius
```

## 6. Check with a real router

1. In the dashboard add a router and paste the setup command into a **test** MikroTik.
2. Buy the cheapest package on the tenant portal, or redeem a voucher.
3. The router should log the customer in without any manual step. In `freeradius -X` you will see the
   Access-Request and the Accept.
4. After a few minutes the session appears in `radacct` (`SELECT * FROM radacct ORDER BY radacctid DESC LIMIT 5;`).

Try it on one RouterOS v6 router and one v7 router. The setup script is written for both, but that is exactly
the kind of thing only a real router proves.

## Things to know

- **One shared secret.** Every router uses the same secret, because routers behind carrier NAT do not have a
  stable address that could identify them. What stops one ISP using another ISP's customers is the
  `NAS-Identifier` condition on every login, not the secret. Treat `RADIUS_SECRET` like a password, and use a
  long random value.
- **Rate-limit the RADIUS ports** at the firewall if you see abuse, and consider `fail2ban` on the FreeRADIUS log.
- **Kicking a customer immediately** is done with the router agent (`kick_user` command, runs within a minute),
  not by RADIUS Disconnect messages, because those cannot reach a router behind NAT.
- **Logins are self-cleaning.** `php artisan radius:prune` removes logins that expired more than a day ago. It
  is scheduled daily.
- **Backups.** The `rad*` tables are part of the Laravel database, so the normal database backup covers them.
