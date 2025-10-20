# Barber Queue App — Build TODO

> Goal: Ship a lightweight PHP 8.3 + MySQL app for DreamHost VPS (Apache) that manages walk‑in rotation with Google/Apple OAuth and a TV read‑only view.

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

- [ ] Create `public/index.php` with simple router (`/login`, `/queue`, `/settings`, `/tv`)
- [ ] Create layout includes: `views/_layout_header.php`, `views/_layout_footer.php`
- [ ] Stub views: `views/login.php`, `views/queue.php`, `views/settings.php`, `views/tv.php`
- [ ] Add base CSS (`assets/css/base.css`, `assets/css/tv.css`) and empty JS (`assets/js/app.js`, `assets/js/tv.js`, `assets/js/auth.js`)

## Phase 3 — OAuth (Google & Apple)

- [ ] `auth/google_start.php` (redirect to Google OAuth)
- [ ] `auth/google_callback.php` (exchange code → tokens → profile; upsert user; start session)
- [ ] `auth/apple_start.php` (auth request with proper scopes)
- [ ] `auth/apple_callback.php` (verify id_token; upsert user; start session)
- [ ] `auth/logout.php` (destroy session)
- [ ] Protect routes: `/queue` and `/settings` require login; `/settings` requires owner role

## Phase 4 — Models & Queue Logic

- [ ] `includes/barber_model.php` (CRUD, list ordered by `position`, reorder)
- [ ] `includes/settings_model.php` (get/set title, logo, theme, tv_token)
- [ ] `includes/queue_logic.php` implementing transitions: - available → busy_walkin: move to bottom, `timer_start=NOW()` - busy_walkin → busy_appointment: keep position, restart timer - busy_appointment → available: keep position, clear timer
- [ ] Normalize positions after any change (1..N)

## Phase 5 — API Endpoints

- [ ] `/api/barbers.php?action=list` (GET): return ordered list + `server_time`
- [ ] `/api/barbers.php?action=status` (POST): apply transition rules
- [ ] `/api/barbers.php?action=order` (POST): manual reorder (array of IDs)
- [ ] `/api/settings.php?action=get` (GET): return title/logo/theme
- [ ] `/api/settings.php?action=save` (POST, owner): update settings
- [ ] `/api/settings.php?action=regenerate_tv_token` (POST, owner): rotate token
- [ ] Add `api/auth_check.php` to validate session/role or `tv_token` for read-only endpoints

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

- [ ] Form to edit title, theme (light/dark), optional logo URL
- [ ] Barber management: add/edit/remove; persist `position` on save
- [ ] Button: Regenerate TV token (writes to DB and shows new URL)
- [ ] CSRF protect all POST forms

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

---

## Phase 4 — Models & Queue Logic

- [ ] `includes/barber_model.php` (CRUD, list ordered by `position`, reorder)
- [ ] `includes/settings_model.php` (get/set title, logo, theme, tv_token)
- [ ] `includes/queue_logic.php` implementing transitions: - available → busy_walkin: move to bottom, `timer_start=NOW()` - busy_walkin → busy_appointment: keep position, restart timer - busy_appointment → available: keep position, clear timer
- [ ] Normalize positions after any change (1..N)

**Files to create**

- `includes/barber_model.php`, `includes/settings_model.php`, `includes/queue_logic.php`

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

- [ ] Render barber cards with name + status chip + small timer placeholder
- [ ] Click behavior cycles statuses: Available → Busy‑Walk‑In → Busy‑Appointment → Available
- [ ] On click, POST to `/api/barbers.php?action=status` then refresh list
- [ ] Poll `/api/barbers.php?action=list` every `ui.poll_ms` ms; diff-update DOM (avoid flicker)
- [ ] Color coding: green (available), red (busy-walk-in), orange (busy-appointment), gray (inactive)
- [ ] Client timer: compute elapsed using `busy_since` & `server_time`

**Files touched**

- `public/views/queue.php`, `public/assets/js/app.js`, `public/assets/css/base.css`

**Definition of Done (Phase 6)**

- [ ] Clicking a card updates status and the list reflects server order within 1 poll cycle
- [ ] Timers show mm:ss and reset when back to Available

---

## Phase 7 — TV Mode (Read‑Only)

- [ ] `views/tv.php` accepts `?token=...`; validate against settings
- [ ] Large typography, no interaction; status colors only
- [ ] `assets/js/tv.js` polls list every `ui.poll_ms` ms and re-renders
- [ ] Admin button to generate/rotate TV token in settings

**TV Token Flow**

- Token stored in `settings.tv_token`
- Public URL: `https://yourdomain.com/tv?token=XYZ`
- Rotating token immediately invalidates old links
- TV endpoints must be read-only

**Definition of Done (Phase 7)**

- [ ] TV view renders without session when a valid token is provided
- [ ] Queue updates visible within one poll cycle

---

## Phase 8 — Settings (Owner/Admin)

- [ ] Form to edit title, theme (light/dark), optional logo URL
- [ ] Barber management: add/edit/remove; persist `position` on save
- [ ] Button: Regenerate TV token (writes to DB and shows new URL)
- [ ] CSRF protect all POST forms

**Files touched**

- `public/views/settings.php`, `api/settings.php`, `includes/settings_model.php`

**Definition of Done (Phase 8)**

- [ ] Saving settings persists and reflects in header/theme
- [ ] Regenerating token updates DB and invalidates old TV URL

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
