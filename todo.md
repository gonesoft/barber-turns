# Barber Queue App — Build TODO

> Goal: Ship a lightweight PHP 8.3 + MySQL app for DreamHost VPS (Apache) that manages walk‑in rotation with Google/Apple OAuth and a TV read‑only view.
> **Role Policy:** All new Google/Apple logins are **Viewer (read‑only)** by default. Only **Owner/Admin** can promote to Front‑Desk or Owner.

## Phase 0 — Repo & Environment (Day 0)

- [x] Initialize Git repo and basic README with project summary and PRD link
- [x] Create base folders: `public/`, `api/`, `auth/`, `includes/`, `sql/`, `public/assets/{css,js,img}`, `public/views/`
- [x] Add `.gitignore` (ignore `includes/config.php`, `/secrets`, vendor caches, macOS files)
- [x] Add `public/.htaccess` for clean URLs + static caching
- [x] Prepare `sql/database.sql` and import into MySQL (DreamHost)

## Phase 1 — Config & DB Wiring

- [x] Add `includes/config.sample.php` and copy to `includes/config.php`
- [x] Implement `includes/db.php` (PDO factory)
- [x] Implement `includes/session.php` (secure session init)
- [x] Implement `includes/auth.php` (`require_login`, `require_owner`, `current_user`)
- [x] Implement `includes/security.php` (CSRF helpers, sanitize helpers)
- [x] Seed `settings` row (id=1) and optional demo barbers

## Phase 2 — Minimal Router & Views Skeleton

- [x] Create `public/index.php` with simple router (`/login`, `/queue`, `/settings`, `/tv`)
- [x] Create layout includes: `views/_layout_header.php`, `views/_layout_footer.php`
- [x] Stub views: `views/login.php`, `views/queue.php`, `views/settings.php`, `views/tv.php`
- [x] Add base CSS (`assets/css/base.css`, `assets/css/tv.css`) and empty JS (`assets/js/app.js`, `assets/js/tv.js`, `assets/js/auth.js`)

## Phase 3 — OAuth (Google & Apple)

## Phase L — Local Username/Password Login (NEW)

- [x] Add login UI fields to `views/login.php`: username/email + password; show Google/Apple buttons beneath.
- [x] Create `auth/local_login.php` (POST) to authenticate and start session.
- [x] Add `includes/passwords.php` with helpers: `hash_password`, `verify_password` (Argon2id).
- [x] Update `includes/auth.php`: add `login_user($user)`, update `require_login()` to allow either auth path.
- [x] Update router to handle POST from `/login` form to `auth/local_login.php`.
- [x] Throttle local login attempts (basic per IP/session).
- [x] Update `users` table: add `username`, `password_hash`, `last_login_at` (see PRD v1.3 SQL).
- [x] Document a one‑time script to generate a password hash and SQL to insert an Owner.

**Definition of Done (Phase L)**

- [x] Manual DB insert of Owner can log in locally and reaches `/queue`.
- [x] Wrong password is rejected with an error; no session is created.
- [x] OAuth continues to function and new OAuth users are Viewer by default.

- [x] `auth/google_start.php` (redirect to Google OAuth)
- [x] `auth/google_callback.php` (exchange code → tokens → profile; upsert user; start session)
- [x] `auth/apple_start.php` (auth request with proper scopes)
- [x] `auth/apple_callback.php` (verify id_token; upsert user; start session)
- [x] `auth/logout.php` (destroy session)
- [x] Protect routes: `/queue` and `/settings` require login; `/settings` requires owner role

## Phase 4 — Models & Queue Logic

- [x] `includes/barber_model.php` (CRUD, list ordered by `position`, reorder)
- [x] `includes/settings_model.php` (get/set title, logo, theme, tv_token)
- [x] `includes/queue_logic.php` implementing transitions: - available → busy_walkin: move to bottom, `timer_start=NOW()` - busy_walkin → busy_appointment: keep position, restart timer - busy_appointment → available: keep position, clear timer
- [x] Normalize positions after any change (1..N)

## Phase 5 — API Endpoints

- [x] `/api/barbers.php?action=list` (GET): return ordered list + `server_time`
- [x] `/api/barbers.php?action=status` (POST): apply transition rules
- [x] `/api/barbers.php?action=order` (POST): manual reorder (array of IDs)
- [x] `/api/settings.php?action=get` (GET): return title/logo/theme
- [x] `/api/settings.php?action=save` (POST, owner): update settings
- [x] `/api/settings.php?action=regenerate_tv_token` (POST, owner): rotate token
- [x] Add `api/auth_check.php` to validate session/role or `tv_token` for read-only endpoints

## Phase 6 — Interactive Queue UI (Front-Desk)

- [x] Render barber cards with name + status chip + small timer placeholder
- [x] Click behavior cycles statuses: Available → Busy‑Walk‑In → Busy‑Appointment → Available
- [x] On click, POST to `/api/barbers.php?action=status` then refresh list
- [x] Poll `/api/barbers.php?action=list` every 3s; diff-update DOM (avoid full re-render flicker)
- [x] Color coding: green (available), red (busy-walk-in), orange (busy-appointment), gray (inactive)
- [x] Client timer: compute elapsed using `busy_since` & `server_time`

## Phase 7 — TV Mode (Read-Only)

- [x] `views/tv.php` accepts `?token=...`; validate against settings
- [x] Large typography, no interaction; status colors only
- [x] `assets/js/tv.js` polls list every 3s and re-renders
- [x] Admin button to generate/rotate TV token in settings

## Phase 8 — Settings (Owner/Admin)

- [x] Form to edit title, theme (light/dark), optional logo URL
- [x] Barber management: add/edit/remove; persist `position` on save
- [x] Button: Regenerate TV token (writes to DB and shows new URL)
- [x] CSRF protect all POST forms

## Phase 9 — Security & Hardening

- [x] Set secure session cookie flags; consider forcing HTTPS
- [x] Validate/escape all output in views (XSS safe)
- [x] Validate inputs for API endpoints; return proper HTTP codes
- [x] Rate-limit status updates (basic in-memory/session throttle)

## Phase 10 — Styling & UX Polish

- [x] Responsive card layout for phone/tablet/desktop
- [x] Smooth CSS transitions for reorder/status change
- [x] Sticky header: shop title + small logo + logout
- [x] Dark mode toggle (persist in settings)

## Phase 11 — Deployment (DreamHost VPS)

- [x] Point domain docroot to `/public`
- [x] Upload files via SFTP/SSH
- [x] Import `sql/database.sql`
- [x] Configure `includes/config.php` (DB + OAuth + security)
- [x] Verify `/login` → `/queue` flow
- [x] Verify `/tv?token=...` read-only display

- [x] Create `public/robots.txt` to block search indexing for this subdomain.

**robots.txt contents**

```txt
User-agent: *
Disallow: /

# Prevents all crawlers (Google, Bing, etc.) from indexing this subdomain.
# Place at: https://your-subdomain.yourdomain.com/robots.txt
```

**Optional (for Apache config / .htaccess)**

```apache
# Add header to reinforce noindex
Header set X-Robots-Tag "noindex, nofollow"
```

## Phase 12 — QA & Acceptance

- [x] Multi-user test: two browsers keep queue in sync within 5s
- [x] Verify status transitions obey rotation rules
- [x] Verify manual reorder persists
- [x] Verify timers start/clear correctly
- [x] Verify TV token access, and token rotation invalidates old links
- [x] Accessibility pass (contrast, keyboard focus on buttons)

## Phase 13 — Post-Launch

- [x] Create nightly DB backup (DreamHost cron + `mysqldump`)
- [x] Add health check endpoint for uptime monitoring
- [x] Collect feedback; plan v1.1 features (customer capture, analytics, multi-location)

---

### Quick Smoke Test Checklist

- [x] Login (Google/Apple) works and session persists
- [x] Add 3 barbers; verify initial order (1..N)
- [x] Toggle top barber to Busy‑Walk‑In → moves to bottom
- [x] Toggle next to Busy‑Appointment → stays in place; timer starts
- [x] Toggle back to Available → stays in place; timer clears
- [x] TV mode reflects changes within 3s
- [x] Settings update title/theme and reflect in UI

# Barber Queue App — Build TODO (Enhanced for GPT‑Codex‑5)

> Goal: Ship a lightweight PHP 8.3 + MySQL app for DreamHost VPS (Apache) that manages walk‑in rotation with Google/Apple OAuth and a TV read‑only view.
>
> **Environment & Secrets**
>
> - PHP 8.3, Apache with `mod_rewrite` enabled, MySQL 8.x
> - Domain points to `public/` as document root
> - Copy `includes/config.sample.php` → `includes/config.php` and fill:
>   - DB creds (host/port/name/user/pass)
>   - OAuth (Google Client ID/Secret, Apple Service ID/Team ID/Key ID/`AuthKey_XXXXXX.p8` path outside web root)
>   - Security: `session_*`, `tv_token_bytes`, CSRF header key
> - Apple `.p8` private key should live outside web root (e.g., `/home/USER/secrets/`)
> - Set `ui.poll_ms` (single source of truth) in `includes/config.php`

---

## Phase 13 — Post‑Launch

- [x] Create nightly DB backup (DreamHost cron + `mysqldump`)
- [x] Add health check endpoint for uptime monitoring
- [x] Collect feedback; plan v1.1 features (customer capture, analytics, multi‑location)

---

### Quick Smoke Test Checklist

- [x] Login (Google/Apple) works and session persists
- [x] Add 3 barbers; verify initial order (1..N)
- [x] Toggle top barber to Busy‑Walk‑In → moves to bottom
- [x] Toggle next to Busy‑Appointment → stays in place; timer starts
- [x] Toggle back to Available → stays in place; timer clears
- [x] TV mode reflects changes within one poll cycle
- [x] Settings update title/theme and reflect in UI
- [x] Log in as a new user (Viewer): UI is read-only and POSTs return 403.

# Barber Queue App — Build TODO (Containerized + Root Entry + Tests)

> Goal: Ship a lightweight PHP 8.3 + MySQL app for DreamHost VPS (Apache). Develop & test locally via Docker. **App runs from domain root**, not `/public`.  
> **Role Policy:** New Google/Apple logins are **Viewer (read-only)** by default. Only **Owner/Admin** can promote roles.

## Phase A — Containerized Local Dev (NEW)

- [x] Add `Dockerfile` (base: `php:8.3-apache`), enable `mod_rewrite`, install `pdo_mysql mbstring curl zip`.
- [x] Add `docker-compose.yml` with services: `app` (ports `8080:80`) and `db` (MySQL 8, port `33060:3306`).
- [x] Add `docker/apache/vhost.conf` serving from `/var/www/html` (repo root). Include rewrite rules.
- [x] Add `docker/php/php.ini` with sane defaults.
- [x] Mount project root into container `/var/www/html`.
- [x] Environment variables for DB and OAuth (`APP_*`).
- [x] Commands: `docker compose up -d`, `docker compose down`, `docker compose logs -f app`.

**Definition of Done (A)**

- [x] `http://localhost:8080/` loads the app entry and `GET /api/barbers.php?action=list` works against container DB.

## Phase B — Config Over Env (NEW)

- [x] Create `includes/config_env.php` to read `APP_*` env vars.
- [x] Modify `includes/config.php` loader: prefer env (`config_env.php`), else file config.
- [x] Ensure DB host resolves to `db` (Docker) and to DreamHost hostname in prod.

**Definition of Done (B)**

- [x] Changing env in `docker-compose.yml` modifies app config without code edits.

## Phase C — Root Entry Harden (NEW)

- [x] Ensure single root entry `index.php` uses `bootstrap.php` to set `APP_ROOT`/`INC_PATH`.
- [x] Update all API/Auth scripts to include robust root resolver.
- [x] Root `.htaccess`: rewrites, block `/includes`, set `X-Robots-Tag`.
- [x] Assets referenced with absolute paths (`/assets/...`).

**Definition of Done (C)**

- [x] App works both in Docker and when uploaded via FTP to domain root.


### Make targets (optional)

- [x] `Makefile` with `up`, `down`, `logs`, `test`, `sh` (shell into container)
