# TrinetPay — SaaS Hotspot Billing Platform
### Unified Technical Specification

---

## 1. What We Are Building

A **cloud-based, multi-tenant WiFi hotspot billing platform** at `trinetpay.online`, targeting small ISPs in Tanzania and East Africa. It is the same concept as HotspotSystem or WISPHUB — but built for the local market, priced in TZS, and integrated with local mobile money providers (PalmPesa, Airtel Money, M-Pesa Tanzania).

**The value proposition:** A small ISP owner signs up, connects their MikroTik router in minutes via a wizard, and immediately has a working payment portal — without writing a single line of code.

---

## 2. Existing Foundation (What We Already Have)

The codebase at this repository is a **working single-tenant captive portal** for one router. It is the starting point.

| Component | File | What It Does |
|---|---|---|
| Payment flow | `app/Http/Controllers/PaymentController.php` | Renders portal, initiates PalmPesa payment, handles webhook, creates MikroTik user |
| PalmPesa client | `app/Services/PalmPesaService.php` | STK push, status polling, phone formatting |
| MikroTik client | `app/Services/MikrotikService.php` | Direct RouterOS socket API (port 8728), creates hotspot users |
| Portal UI | `resources/views/portal.blade.php` | Mobile-responsive single page with package selection and JS polling |
| Package config | `config/package.php` | Hardcoded Bronze/Silver/Gold tiers → must move to DB |
| Router config | `config/services.php` | Hardcoded MikroTik IP/credentials → must move to DB |

**Current limitations to fix:**
- All MikroTik and PalmPesa credentials are hardcoded in `.env` / config files
- No persistent transaction records (file cache only, 30 min TTL)
- No tenant concept — one router, one ISP, forever
- No admin panel, no auth, no branding customization

---

## 3. System Architecture

```
trinetpay.online              ← Marketing landing page + ISP signup
isp1.trinetpay.online         ← ISP 1's captive portal (reads DB by subdomain)
isp2.trinetpay.online         ← ISP 2's captive portal (reads DB by subdomain)
dashboard.trinetpay.online    ← ISP owner management panel (Filament)
```

### How The Portal Resolves Tenants

```
[Customer's phone connects to local MikroTik]
       ↓  (router redirects to portal URL)
[isp1.trinetpay.online?mac=...&ip=...&router=...]
       ↓  (Laravel TenantMiddleware reads subdomain "isp1")
[DB lookup: tenants WHERE subdomain = "isp1"]
       ↓
[Load: tenant's packages, branding, PalmPesa key, router IP]
       ↓
[Render portal.blade.php with tenant-specific data]
```

### Payment + MikroTik Flow

```
[Customer selects package + enters phone]
       ↓
[POST /api/payment/initiate]
       ↓
[PalmPesaService → STK push to customer's phone]
       ↓
[Customer enters PIN on their phone]
       ↓
[PalmPesa webhook → POST /api/payment/callback]
       ↓
[Store transaction in DB (transactions table)]
       ↓
[MikrotikService → create hotspot user on tenant's router]
       ↓
[Return token to frontend → customer is online]
```

### Future: FreeRADIUS for Fleet Management (Phase 2+)

For ISPs with many routers, RADIUS is more scalable than per-router API calls:

```
[Customer] → [Local MikroTik] → [FreeRADIUS Server]
                                        ↑
                               Reads/writes radcheck
                               and radreply tables
                                        ↑
                            [Laravel MySQL Database]
```

Laravel writes user credentials into RADIUS tables directly. On payment success, it sends a `Disconnect-Request (PoD)` packet to the router, which forces the customer's device to re-authenticate and enter the authenticated RADIUS session. This means no per-router API calls for provisioning — just a DB write + a single UDP packet.

---

## 4. Database Schema

### All new migrations to create:

#### `tenants`
```sql
id, name, subdomain (unique), logo_path, status (active/suspended/trial),
created_at, updated_at
```

#### `tenant_routers`
```sql
id, tenant_id (FK), name, router_ip, username, password (encrypted),
port (default 8728), nas_identifier (unique, for RADIUS), status, last_seen_at,
created_at, updated_at
```

#### `tenant_packages`
```sql
id, tenant_id (FK), name, price (TZS), duration_hours, speed_down_mbps,
speed_up_mbps, data_cap_mb (nullable = unlimited), mikrotik_profile,
validity_type (strict|first_login), is_active, created_at, updated_at
```

#### `tenant_settings`
```sql
id, tenant_id (FK unique), payment_gateway (palmpesa|airtel|mpesa),
payment_number, palmpesa_api_key (encrypted), palmpesa_user_id,
brand_color (hex), tagline, custom_logo_path, created_at, updated_at
```

#### `transactions`
```sql
id, tenant_id (FK), router_id (FK), package_id (FK), phone, amount,
status (pending|completed|failed), palmpesa_order_id, palmpesa_txn_id,
voucher_code, expires_at, created_at, updated_at
```

#### `vouchers`
```sql
id, tenant_id (FK), package_id (FK), code (unique), batch_ref,
used_at (nullable), used_by_phone (nullable), created_at
```

#### `tenant_users`
```sql
id, tenant_id (FK), name, email (unique), password, role (owner|agent),
created_at, updated_at
```

#### `agent_wallets`
```sql
id, tenant_user_id (FK unique), balance (decimal), updated_at
```

#### `agent_wallet_transactions`
```sql
id, wallet_id (FK), type (credit|debit), amount, reference, created_at
```

---

## 5. Feature Modules (Build Order)

### Phase 1 — Foundation (MVP)

#### 1.1 Database Migrations
Create all tables above. Move packages from `config/package.php` into `tenant_packages`. Move router credentials from `.env` into `tenant_routers` (encrypt passwords using Laravel's `encrypt()`).

#### 1.2 Tenant Registration
- Public form at `trinetpay.online/register`
- Fields: Business name, owner name, email, password, phone
- Auto-assigns subdomain from business name slug (e.g., "Juma WiFi" → `juma-wifi`)
- Creates `tenant` + `tenant_user` (role=owner) + `tenant_settings` (empty)
- Sends email verification

#### 1.3 Tenant Middleware
```php
// app/Http/Middleware/ResolveTenant.php
// Reads request host, extracts subdomain, loads Tenant from DB
// Sets app('tenant') singleton for use throughout the request
// Returns 404 if subdomain not found or tenant suspended
```
Registered in `bootstrap/app.php` for the `web` and `api` groups.

#### 1.4 Multi-Tenant Captive Portal
Refactor `PaymentController` to:
- Read packages from `tenant_packages WHERE tenant_id = tenant()->id`
- Read PalmPesa credentials from `tenant_settings`
- Read MikroTik credentials from `tenant_routers` (matched by `nas_id` query param from router)
- Write all transactions to `transactions` table (no more file cache as primary store)
- Apply tenant branding (logo, color, name) to portal view

#### 1.5 Onboarding Wizard (3-step)
After registration, ISP owner is guided through:
1. **Router Setup** — enter router IP, API username, password, port. Test connection button calls `MikrotikService::connect()`. Saves to `tenant_routers`. Shows the RouterOS setup script to paste into terminal.
2. **Package Setup** — create at least one package (name, price, duration, speed, MikroTik profile name). Pre-filled with sensible defaults.
3. **Payment Setup** — enter PalmPesa API key, user ID, and payment number. Test button initiates a dummy 1 TZS request.

---

### Phase 2 — ISP Dashboard

#### 2.1 Dashboard Home (Filament Panel)
- Today's revenue (sum of completed transactions)
- Active sessions count (hotspot users currently online via MikroTik API)
- Total transactions this month
- Revenue trend chart (last 7 days)
- Router online/offline status badges

#### 2.2 Transaction History
- Table: date, phone, package, amount, status, voucher code
- Filter by date range, status, router
- Export to CSV

#### 2.3 Router Fleet Manager
- List all routers with online/offline status
- Add/edit/remove routers
- Per-router: last seen timestamp, session count, revenue today
- Reboot router button (calls `/system/reboot` via MikroTik API)

#### 2.4 Package Manager
- CRUD for packages
- Toggle active/inactive

#### 2.5 Settings
- Upload logo
- Set brand color
- Update PalmPesa credentials
- Update payment number
- Business profile (name, tagline, contact info shown on portal)

#### 2.6 Portal Preview
Live preview of what the captive portal looks like with current branding applied.

---

### Phase 3 — Voucher & Agent POS System

#### 3.1 Bulk Voucher Generator
- ISP owner selects package, quantity (10–5,000)
- Laravel generates unique alphanumeric codes (e.g., `WIFI-A3K9-PL27`)
- Stores all in `vouchers` table with `batch_ref`
- Generates downloadable PDF (grid layout, printable as scratch cards) via Laravel DomPDF

#### 3.2 Agent Portal
- ISP owner creates agent accounts (role=agent) with login credentials
- Agent gets a simple mobile-web app (lightweight view, no Filament)
- Agent pre-funds wallet by paying ISP owner directly (ISP owner credits wallet manually from dashboard)
- Agent workflow:
  1. Customer hands cash to agent
  2. Agent enters customer's phone number + selects package
  3. Laravel checks agent wallet balance ≥ package price
  4. Deducts from wallet, creates transaction, sends SMS voucher to customer
- Agent can view own transaction history and wallet balance

---

### Phase 4 — ISP Billing (Platform Revenue)

#### 4.1 Subscription Plans
- `platform_plans` table: name, price_tzs, max_routers, features JSON
- Default plans: Starter (10,000 TZS/mo, 1 router), Growth (25,000 TZS/mo, 5 routers), Pro (50,000 TZS/mo, unlimited)

#### 4.2 Billing Cycle
- Monthly cron job checks all active tenants
- Generates invoice per tenant
- Sends STK push to tenant owner's phone via PalmPesa
- Suspends tenant (sets `status = suspended`) if payment not received within 7-day grace period
- Suspended tenants' portals show "Service Suspended" page instead of payment portal

#### 4.3 Super-Admin Panel
- Separate Filament panel for platform owner (TrinetPay admin)
- View all tenants, their status, monthly revenue
- Manually suspend/activate tenants
- View platform-wide revenue
- Impersonate tenant (for support)

---

## 6. Technical Decisions

### Multi-tenancy Approach
**Custom middleware** (not a package like `spatie/laravel-multitenancy`). The subdomain resolves to a tenant record, which is bound as a singleton. All tenant-scoped queries use `WHERE tenant_id = ?` — no separate databases per tenant.

Rationale: Simpler to operate, cheaper to host, easier to query across tenants for billing/analytics.

### Packages to Add
```json
"filament/filament": "^3.2",
"barryvdh/laravel-dompdf": "^3.0",
"laravel/horizon": "^5.0"
```

- **Filament v3** — ISP owner dashboard + super-admin panel (CRUD, charts, forms)
- **DomPDF** — voucher PDF generation
- **Horizon** — queue monitoring (payment webhooks, router jobs must not block HTTP)

### Queue Strategy
All MikroTik operations and PalmPesa status polling must run in queued jobs:
- `ProcessPaymentCallbackJob` — triggered by PalmPesa webhook
- `CreateHotspotUserJob` — creates MikroTik user after payment
- `RouterHeartbeatJob` — scheduled every 5 minutes, pings each router, updates `last_seen_at`

### Security
- Router passwords: stored encrypted with Laravel `encrypt()` (AES-256-CBC)
- PalmPesa API keys: stored encrypted in `tenant_settings`
- Tenant portal API routes: protected by `VerifyCsrfToken` exclusion for callback only
- Agent wallet: all deductions wrapped in DB transactions to prevent race conditions
- Subdomain SSRF: `router_ip` input must be validated against private IP ranges only (RFC 1918: 10.x, 172.16.x, 192.168.x) — reject public IPs to prevent SSRF

---

## 7. MikroTik Router Setup Script

When an ISP adds a router, the wizard shows them this script to run once in the MikroTik terminal:

```routeros
# TrinetPay Setup Script — paste this into your MikroTik terminal

# 1. Enable API
/ip service enable api
/ip service set api port=8728

# 2. Create API user
/user add name=trinetpay password=<auto-generated> group=full

# 3. Walled garden — allow portal and payment domains through without login
/ip hotspot walled-garden add dst-host=trinetpay.online
/ip hotspot walled-garden add dst-host=*.trinetpay.online
/ip hotspot walled-garden add dst-host=palmpesa.drmlelwa.co.tz

# 4. Set portal redirect URL
/ip hotspot profile set hsprof1 login-by=http-chap \
  html-directory-override="" \
  http-proxy=0.0.0.0:0 \
  hotspot-address=0.0.0.0 \
  dns-name="" \
  rate-limit=""

# Note: Laravel generates this script dynamically with the tenant's subdomain
# and auto-generated API credentials
```

The script is generated per-tenant with their specific subdomain and auto-generated credentials — the ISP owner just pastes and runs.

---

## 8. Revenue Model

| Model | Rate | When to use |
|---|---|---|
| Monthly per-router subscription | 10,000–30,000 TZS/month | Primary model |
| Per-transaction fee | 2–5% of each payment | Optional add-on or alternative |
| Both | Small monthly + small cut | Premium/Pro tier |

---

## 9. What Changes in Existing Code

| Current | SaaS Version |
|---|---|
| `config/package.php` hardcoded tiers | `tenant_packages` table, per-tenant |
| `config/services.php` hardcoded MikroTik | `tenant_routers` table, encrypted |
| `config/services.php` hardcoded PalmPesa | `tenant_settings` table, encrypted, per-tenant |
| File cache for transactions | `transactions` table, persistent |
| `PaymentController` reads from config | Reads from DB via resolved tenant |
| No auth | Filament auth for tenant panel |
| No branding | Logo, color, name from `tenant_settings` |
| Single `portal.blade.php` | Same view, but all data injected by tenant |
| No routing by subdomain | `ResolveTenant` middleware runs on every request |

---

## 10. Implementation Checklist

- [ ] **Phase 1.1** — Write all DB migrations
- [ ] **Phase 1.2** — Tenant registration page + controller
- [ ] **Phase 1.3** — `ResolveTenant` middleware + tenant helper/singleton
- [ ] **Phase 1.4** — Refactor `PaymentController` + services to be tenant-aware
- [ ] **Phase 1.5** — Onboarding wizard (3 steps)
- [ ] **Phase 2.1** — Install Filament, scaffold ISP dashboard
- [ ] **Phase 2.2** — Transaction history table
- [ ] **Phase 2.3** — Router fleet manager
- [ ] **Phase 2.4** — Package manager CRUD
- [ ] **Phase 2.5** — Settings page (branding + credentials)
- [ ] **Phase 3.1** — Voucher generator + PDF export
- [ ] **Phase 3.2** — Agent portal + wallet system
- [ ] **Phase 4.1** — Platform subscription plans
- [ ] **Phase 4.2** — Monthly billing cron + suspension logic
- [ ] **Phase 4.3** — Super-admin panel
