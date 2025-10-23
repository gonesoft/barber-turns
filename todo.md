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

- [ ] Render barber cards with name + status chip + small timer placeholder
- [ ] Click behavior cycles statuses: Available → Busy‑Walk‑In → Busy‑Appointment → Available
- [ ] On click, POST to `/api/barbers.php?action=status` then refresh list
- [ ] Poll `/api/barbers.php?action=list` every 3s; diff-update DOM (avoid full re-render flicker)
- [ ] Color coding: green (available), red (busy-walk-in), orange (busy-appointment), gray (inactive)
- [ ] Client timer: compute elapsed using `busy_since` & `server_time`

## Phase 7 — TV Mode (Read-Only)

- [ ] `views/tv.php` accepts `?token=...`; validate against settings
- [ ] Large typography, no interaction; status colors only
- [ ] `assets/js/tv.js` polls list every 3s and re-renders
- [ ] Admin button to generate/rotate TV token in settings

## Phase 8 — Settings (Owner/Admin)

- [x] Form to edit title, theme (light/dark), optional logo URL
- [x] Barber management: add/edit/remove; persist `position` on save
- [x] Button: Regenerate TV token (writes to DB and shows new URL)
- [x] CSRF protect all POST forms

## Phase 9 — Security & Hardening

- [ ] Set secure session cookie flags; consider forcing HTTPS
- [ ] Validate/escape all output in views (XSS safe)
- [ ] Validate inputs for API endpoints; return proper HTTP codes
- [ ] Rate-limit status updates (basic in-memory/session throttle)

## Phase 10 — Styling & UX Polish

- [ ] Responsive card layout for phone/tablet/desktop
- [ ] Smooth CSS transitions for reorder/status change
- [ ] Sticky header: shop title + small logo + logout
- [ ] Dark mode toggle (persist in settings)

## Phase 11 — Deployment (DreamHost VPS)

- [ ] Point domain docroot to `/public`
- [ ] Upload files via SFTP/SSH
- [ ] Import `sql/database.sql`
- [ ] Configure `includes/config.php` (DB + OAuth + security)
- [ ] Verify `/login` → `/queue` flow
- [ ] Verify `/tv?token=...` read-only display

- [ ] Create `public/robots.txt` to block search indexing for this subdomain.

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

- [ ] Multi-user test: two browsers keep queue in sync within 5s
- [ ] Verify status transitions obey rotation rules
- [ ] Verify manual reorder persists
- [ ] Verify timers start/clear correctly
- [ ] Verify TV token access, and token rotation invalidates old links
- [ ] Accessibility pass (contrast, keyboard focus on buttons)

## Phase 13 — Post-Launch

- [ ] Create nightly DB backup (DreamHost cron + `mysqldump`)
- [ ] Add health check endpoint for uptime monitoring
- [ ] Collect feedback; plan v1.1 features (customer capture, analytics, multi-location)

---

### Quick Smoke Test Checklist

- [ ] Login (Google/Apple) works and session persists
- [ ] Add 3 barbers; verify initial order (1..N)
- [ ] Toggle top barber to Busy‑Walk‑In → moves to bottom
- [ ] Toggle next to Busy‑Appointment → stays in place; timer starts
- [ ] Toggle back to Available → stays in place; timer clears
- [ ] TV mode reflects changes within 3s
- [ ] Settings update title/theme and reflect in UI

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

## Phase 0 — Repo & Environment (Day 0)

- [ ] Initialize Git repo and basic README with project summary and PRD link
- [ ] Create base folders: `public/`, `api/`, `auth/`, `includes/`, `sql/`, `public/assets/{css,js,img}`, `public/views/`
- [ ] Add `.gitignore` (ignore `includes/config.php`, `/secrets`, vendor caches, macOS files)
- [ ] Add `public/.htaccess` for clean URLs + static caching
- [ ] Prepare `sql/database.sql` and import into MySQL (DreamHost)

**Files to create**

- `public/.htaccess`
- `sql/database.sql`
- `README.md`
- `.gitignore`

**Definition of Done (Phase 0)**

- [ ] Repo exists with folders above
- [ ] MySQL schema imported without errors
- [ ] `public/.htaccess` rewrite active (hit `/anything` routes to `index.php` once added)

---

## Phase 1 — Config & DB Wiring

- [ ] Add `includes/config.sample.php` and copy to `includes/config.php`
- [ ] Implement `includes/db.php` (PDO factory)
- [ ] Implement `includes/session.php` (secure session init)
- [ ] Implement `includes/auth.php` (`require_login`, `require_owner`, `current_user`)
- [ ] Implement `includes/security.php` (CSRF helpers, sanitize helpers)
- [ ] Seed `settings` row (id=1) and optional demo barbers

**Files to create**

- `includes/config.sample.php`, `includes/config.php` (local)
- `includes/db.php`, `includes/session.php`, `includes/auth.php`, `includes/security.php`

**Definition of Done (Phase 1)**

- [ ] `db.php` returns a live PDO connection
- [ ] Sessions start with secure cookie flags
- [ ] `require_login()` and `require_owner()` callable from any script

---

## Phase 2 — Minimal Router & Views Skeleton

- [ ] Create `public/index.php` with simple router (`/login`, `/queue`, `/settings`, `/tv`)
- [ ] Create layout includes: `views/_layout_header.php`, `views/_layout_footer.php`
- [ ] Stub views: `views/login.php`, `views/queue.php`, `views/settings.php`, `views/tv.php`
- [ ] Add base CSS (`assets/css/base.css`, `assets/css/tv.css`) and empty JS (`assets/js/app.js`, `assets/js/tv.js`, `assets/js/auth.js`)

**Files to create**

- `public/index.php`
- `public/views/_layout_header.php`, `public/views/_layout_footer.php`
- `public/views/login.php`, `public/views/queue.php`, `public/views/settings.php`, `public/views/tv.php`
- `public/assets/css/base.css`, `public/assets/css/tv.css`
- `public/assets/js/app.js`, `public/assets/js/tv.js`, `public/assets/js/auth.js`

**Definition of Done (Phase 2)**

- [ ] Navigating to `/login`, `/queue`, `/settings`, `/tv` renders distinct stub pages
- [ ] Base CSS loads; no 404s for assets

---

## Phase 3 — OAuth (Google & Apple)

- [ ] `auth/google_start.php` (redirect to Google OAuth)
- [ ] `auth/google_callback.php` (exchange code → tokens → profile; upsert user; start session)
- [ ] `auth/apple_start.php` (auth request with proper scopes)
- [ ] `auth/apple_callback.php` (verify id_token; upsert user; start session)
- [ ] `auth/logout.php` (destroy session)
- [ ] Protect routes: `/queue` and `/settings` require login; `/settings` requires owner role

**Files to create**

- `auth/google_start.php`, `auth/google_callback.php`
- `auth/apple_start.php`, `auth/apple_callback.php`
- `auth/logout.php`

**Definition of Done (Phase 3)**

- [ ] Logging in via Google creates/fetches `users` row and lands on `/queue`
- [ ] Logging out clears session
- [ ] `/settings` blocks non-owner
- [x] New OAuth users are created with role 'viewer' and email captured.
- [x] UI shows a small "View-only" badge for viewers; interactive elements disabled.

---

## Phase 4 — Models & Queue Logic

- [x] `includes/barber_model.php` (CRUD, list ordered by `position`, reorder)
- [x] `includes/settings_model.php` (get/set title, logo, theme, tv_token)
- [x] `includes/queue_logic.php` implementing transitions: - available → busy_walkin: move to bottom, `timer_start=NOW()` - busy_walkin → busy_appointment: keep position, restart timer - busy_appointment → available: keep position, clear timer
- [x] Normalize positions after any change (1..N)

**Files to create**

- `includes/barber_model.php`, `includes/settings_model.php`, `includes/queue_logic.php`

- [x] Update \`users\` schema: add \`email\` and extend \`role\` enum to ('viewer','frontdesk','owner'); default 'viewer'.

**Definition of Done (Phase 4)**

- [ ] Calling queue logic functions updates DB with correct positions and timers
- [ ] Listing barbers returns normalized 1..N order

---

## Phase 5 — API Endpoints

- [ ] `/api/barbers.php?action=list` (GET): return ordered list + `server_time`
- [ ] `/api/barbers.php?action=status` (POST): apply transition rules
- [ ] `/api/barbers.php?action=order` (POST): manual reorder (array of IDs)
- [ ] `/api/settings.php?action=get` (GET): return title/logo/theme
- [ ] `/api/settings.php?action=save` (POST, owner): update settings
- [ ] `/api/settings.php?action=regenerate_tv_token` (POST, owner): rotate token
- [ ] Add `api/auth_check.php` to validate session/role or `tv_token` for read-only endpoints

**Files to create**

- `api/barbers.php`, `api/settings.php`, `api/index.php` (optional dispatcher)
- `api/auth_check.php`

- [ ] Enforce roles: POST /status and /order require Front‑Desk or Owner; viewers get 403 JSON.

**API Contract (cURL examples)**

```sh
# List barbers (authorized session)
curl -s https://yourdomain.com/api/barbers.php?action=list \
  -H 'Accept: application/json'

# Update status (available|busy_walkin|busy_appointment)
curl -s -X POST https://yourdomain.com/api/barbers.php?action=status \
  -H 'Content-Type: application/json' \
  -d '{"barber_id":2, "status":"busy_walkin"}'

# Manual reorder (array of IDs top→bottom)
curl -s -X POST https://yourdomain.com/api/barbers.php?action=order \
  -H 'Content-Type: application/json' \
  -d '{"order":[3,1,2,4]}'

# Get settings
curl -s https://yourdomain.com/api/settings.php?action=get

# Save settings (owner)
curl -s -X POST https://yourdomain.com/api/settings.php?action=save \
  -H 'Content-Type: application/json' \
  -d '{"title":"Fade Nation","theme":"dark","logo_url":null}'

# Regenerate TV token (owner)
curl -s -X POST https://yourdomain.com/api/settings.php?action=regenerate_tv_token
```

# Example: viewer attempting to change status should receive 403

curl -i -s -X POST https://yourdomain.com/api/barbers.php?action=status \
 -H 'Content-Type: application/json' \
 -d '{"barber_id":1, "status":"busy_walkin"}'

# Expect: HTTP/1.1 403 Forbidden with {"error":"forbidden","reason":"insufficient_role"}

**Expected `list` response shape**

```json
{
  "barbers": [
    {
      "id": 1,
      "name": "Alex",
      "status": "available",
      "position": 1,
      "busy_since": null
    },
    {
      "id": 2,
      "name": "Mia",
      "status": "busy_walkin",
      "position": 2,
      "busy_since": "2025-10-20T15:42:10Z"
    }
  ],
  "server_time": "2025-10-20T15:45:00Z"
}
```

**Definition of Done (Phase 5)**

- [ ] All endpoints return correct HTTP codes and JSON
- [ ] `status` endpoint enforces rotation logic server‑side
- [ ] CSRF protection present for form POSTs (settings) and auth checks for session endpoints

---

## Phase 6 — Interactive Queue UI (Front-Desk)

- [x] Render barber cards with name + status chip + small timer placeholder
- [x] Click behavior cycles statuses: Available → Busy‑Walk‑In → Busy‑Appointment → Available
- [x] On click, POST to `/api/barbers.php?action=status` then refresh list
- [x] Poll `/api/barbers.php?action=list` every `ui.poll_ms` ms; diff-update DOM (avoid flicker)
- [x] Color coding: green (available), red (busy-walk-in), orange (busy-appointment), gray (inactive)
- [x] Client timer: compute elapsed using `busy_since` & `server_time`

**Files touched**

- `public/views/queue.php`, `public/assets/js/app.js`, `public/assets/css/base.css`

**Definition of Done (Phase 6)**

- [x] Clicking a card updates status and the list reflects server order within 1 poll cycle
- [x] Timers show mm:ss and reset when back to Available
- [x] If current user is Viewer, disable click handlers and show 'View‑only' indicator.

---

## Phase 7 — TV Mode (Read-Only)

- [x] `views/tv.php` accepts `?token=...`; validate against settings
- [x] Large typography, no interaction; status colors only
- [x] `assets/js/tv.js` polls list every `ui.poll_ms` ms and re-renders
- [x] Admin button to generate/rotate TV token in settings

**TV Token Flow**

- Token stored in `settings.tv_token`
- Public URL: `https://yourdomain.com/tv?token=XYZ`
- Rotating token immediately invalidates old links
- TV endpoints must be read-only

**Definition of Done (Phase 7)**

- [x] TV view renders without session when a valid token is provided
- [x] Queue updates visible within one poll cycle

---

## Phase 8 — Settings (Owner/Admin)

- [x] Form to edit title, theme (light/dark), optional logo URL
- [x] Barber management: add/edit/remove; persist `position` on save
- [x] Button: Regenerate TV token (writes to DB and shows new URL)
- [x] CSRF protect all POST forms

**Files touched**

- `public/views/settings.php`, `api/settings.php`, `includes/settings_model.php`

### Users (Owner only)

- [x] List users (name, email, provider, role).
- [x] Owner can change a user's role (viewer/frontdesk/owner).
- [x] Add \`/api/users.php\` endpoints: \`list\` (GET, owner), \`set_role\` (POST, owner).

**Definition of Done (Phase 8)**

- [x] Saving settings persists and reflects in header/theme
- [x] Regenerating token updates DB and invalidates old TV URL

---

## Phase 9 — Security & Hardening

- [ ] Set secure session cookie flags; consider forcing HTTPS
- [ ] Validate/escape all output in views (XSS safe)
- [ ] Validate inputs for API endpoints; return proper HTTP codes
- [ ] Rate‑limit status updates (basic in‑memory/session throttle)

**Definition of Done (Phase 9)**

- [ ] Security linters/checks pass (manual code review)
- [ ] No reflected XSS on any view

---

## Phase 10 — Styling & UX Polish

- [ ] Responsive card layout for phone/tablet/desktop
- [ ] Smooth CSS transitions for reorder/status change
- [ ] Sticky header: shop title + small logo + logout
- [ ] Dark mode toggle (persist in settings)

**Definition of Done (Phase 10)**

- [ ] Layout scales gracefully from 360px wide to 4K TV
- [ ] Dark/light themes switch without layout glitches

---

## Phase 11 — Deployment (DreamHost VPS)

- [ ] Point domain docroot to `/public`
- [ ] Upload files via SFTP/SSH
- [ ] Import `sql/database.sql`
- [ ] Configure `includes/config.php` (DB + OAuth + security)
- [ ] Verify `/login` → `/queue` flow
- [ ] Verify `/tv?token=...` read‑only display

- [ ] Create `public/robots.txt` to block search indexing for this subdomain.

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

**Definition of Done (Phase 11)**

- [ ] Site is reachable over HTTPS with working routes and assets

---

## Phase 12 — QA & Acceptance

- [ ] Multi-user test: two browsers keep queue in sync within 5s
- [ ] Verify status transitions obey rotation rules
- [ ] Verify manual reorder persists
- [ ] Verify timers start/clear correctly
- [ ] Verify TV token access, and token rotation invalidates old links
- [ ] Accessibility pass (contrast, keyboard focus on buttons)

**QA Smoke Script (Manual)**

1. Open Browser A (front-desk) and Browser B (TV token).
2. Add 3 barbers and verify order 1..N.
3. On A: Toggle top barber to Busy‑Walk‑In → B shows moved card at bottom within one poll.
4. On A: Toggle next to Busy‑Appointment → B shows card stays in place; timer running.
5. On A: Toggle same card to Available → timer clears; position unchanged.
6. Regenerate TV token and confirm old TV URL stops updating.

- [ ] Viewer cannot POST to protected endpoints (403).

---

## Phase 13 — Post‑Launch

- [ ] Create nightly DB backup (DreamHost cron + `mysqldump`)
- [ ] Add health check endpoint for uptime monitoring
- [ ] Collect feedback; plan v1.1 features (customer capture, analytics, multi‑location)

---

### Quick Smoke Test Checklist

- [ ] Login (Google/Apple) works and session persists
- [ ] Add 3 barbers; verify initial order (1..N)
- [ ] Toggle top barber to Busy‑Walk‑In → moves to bottom
- [ ] Toggle next to Busy‑Appointment → stays in place; timer starts
- [ ] Toggle back to Available → stays in place; timer clears
- [ ] TV mode reflects changes within one poll cycle
- [ ] Settings update title/theme and reflect in UI
- [ ] Log in as a new user (Viewer): UI is read-only and POSTs return 403.

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

## Phase 0 — Repo & Environment (Done)

- [x] Initialize Git repo and README
- [x] Base folders created
- [x] `.gitignore`
- [x] SQL imported

## Phase 1 — Config & DB Wiring (Done)

- [x] `includes/config.php`, `db.php`, `session.php`, `auth.php`, `security.php`
- [x] Seed `settings`

## Phase 2 — Router & Views (Root Entry now)

- [x] `index.php` router at root (`/login`, `/queue`, `/settings`, `/tv`)
- [x] Layout: `views/_layout_header.php`, `_layout_footer.php`
- [x] Views: `views/login.php`, `views/queue.php`, `views/settings.php`, `views/tv.php`
- [x] CSS/JS: `assets/css/base.css`, `assets/css/tv.css`, `assets/js/app.js`, `assets/js/tv.js`, `assets/js/auth.js`

**Definition of Done (Phase 2)**

- [x] Navigating to `/login`, `/queue`, `/settings`, `/tv` renders

## Phase 3 — OAuth (Google & Apple) (Done, recheck roles)

## Phase L — Local Username/Password Login (NEW)

- [ ] Add login UI fields to `views/login.php`: username/email + password; show Google/Apple buttons beneath.
- [ ] Create `auth/local_login.php` (POST) to authenticate and start session.
- [ ] Add `includes/passwords.php` with helpers: `hash_password`, `verify_password` (Argon2id).
- [ ] Update `includes/auth.php`: add `login_user($user)`, update `require_login()` to allow either auth path.
- [ ] Update router to handle POST from `/login` form to `auth/local_login.php`.
- [ ] Throttle local login attempts (basic per IP/session).
- [ ] Update `users` table: add `username`, `password_hash`, `last_login_at` (see PRD v1.3 SQL).
- [ ] Document a one‑time script to generate a password hash and SQL to insert an Owner.

**Definition of Done (Phase L)**

- [ ] Manual DB insert of Owner can log in locally and reaches `/queue`.
- [ ] Wrong password is rejected with an error; no session is created.
- [ ] OAuth continues to function and new OAuth users are Viewer by default.

- [x] OAuth start/callbacks
- [x] Logout
- [x] Protect routes
- [x] Default new users to `viewer`; show View-only UI

## Phase 4 — Models & Queue Logic (Done)

- [x] Barber model, Settings model, Queue logic
- [x] Normalize positions 1..N

## Phase 5 — API Endpoints (Re‑verify under Docker)

- [ ] `GET /api/barbers.php?action=list` (session or `token`)
- [ ] `POST /api/barbers.php?action=status` (FD/Owner only)
- [ ] `POST /api/barbers.php?action=order` (FD/Owner only)
- [ ] `GET /api/settings.php?action=get`
- [ ] `POST /api/settings.php?action=save` (Owner)
- [ ] `POST /api/settings.php?action=regenerate_tv_token` (Owner)
- [ ] `GET /api/users.php?action=list` (Owner), `POST /api/users.php?action=set_role` (Owner)

**Definition of Done (Phase 5)**

- [ ] All endpoints return correct codes/JSON in Docker environment

## Phase 6 — Interactive Queue UI (Front‑Desk)

- [ ] Render barber cards; timers; color coding
- [ ] Click → cycle statuses; POST then refresh list
- [ ] Poll every `ui.poll_ms` ms; diff update
- [ ] If Viewer, disable interaction + badge

## Phase 7 — TV Mode (Read‑Only)

- [ ] `tv.php` with `?token=...` validation
- [ ] `assets/js/tv.js` polling
- [ ] Admin button regenerates token; old URL stops updating

## Phase 8 — Settings (Owner/Admin) (Done)

- [x] Title/theme/logo
- [x] Barber CRUD
- [x] Regenerate TV token
- [x] CSRF protection
- [x] Users tab: list/set role; endpoints present

## Phase 9 — Security & Hardening

- [ ] Secure session flags; HTTPS recommended
- [ ] Escape output (XSS)
- [ ] Validate all API inputs; return proper codes
- [ ] Rate-limit status updates

## Phase 10 — Styling & UX Polish

- [ ] Responsive cards; transitions; sticky header
- [ ] Dark mode toggle (persist)

## Phase 11 — Deployment (DreamHost VPS)

- [ ] Upload only **production files** (exclude `docker/`, `vendor/`, `tests/`, `.env*`)
- [ ] Configure `includes/config.php` with DreamHost DB + OAuth
- [ ] Verify `/login` → `/queue` flow
- [ ] Verify `/tv?token=...` read‑only display
- [ ] Create `robots.txt` in root to block indexing

**robots.txt**

```
User-agent: *
Disallow: /
```

## Phase 12 — Automated Tests (NEW)

- [ ] Add `composer.json` with dev deps: `phpunit/phpunit:^10`.
- [ ] Add `phpunit.xml` and `tests/` tree: `tests/Unit/QueueLogicTest.php`, `tests/Integration/ApiTest.php`.
- [ ] Add `tests/fixtures/*.sql` for seeding.
- [ ] GitHub Actions or local script to run tests in Docker.

**Definition of Done (Phase 12)**

- [ ] `docker compose run --rm app vendor/bin/phpunit` passes

## Phase 13 — QA & Acceptance

- [ ] Manual smoke: two browsers, role checks, timers, token rotation

---

### Make targets (optional)

- [ ] `Makefile` with `up`, `down`, `logs`, `test`, `sh` (shell into container)
