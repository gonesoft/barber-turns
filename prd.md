# Barber Queue Management App (PRD)

**Version:** 1.5  
**Environment:** DreamHost VPS (Apache + PHP 8.3 + MySQL)  
**Deployment:** Root document directory (no `/public`)  
**Platform:** Web (responsive for phone, desktop, TV)  
**Development:** Docker-based local environment + GitHub workflow (protected `main`)

---

## 1. Overview

The Barber Queue App manages **walk-in flow** for a barber shop.  
When a customer arrives without an appointment, the **next available barber** is assigned automatically from a live rotation queue.  

The app is built for:

- **Front-Desk Operators** managing walk-ins.  
- **Owners/Admins** configuring shop details.  
- **TV Displays** showing real-time barber status.

---

## 2. Objectives

1. Provide a **fair, transparent rotation** among barbers.  
2. Enable **quick status updates** with one tap or card flip.  
3. Support **responsive display** across phones, desktops, and TVs.  
4. Use a **lightweight PHP + MySQL** stack deployable via FTP/SSH.  
5. Maintain **cache-safe logout and security isolation** for shared devices.

---

## 3. Core Features

### 3.1 Queue & Rotation
- Each barber appears as a **card** showing:
  - Name  
  - Status: `Available`, `Busy – Walk-In`, `Busy – Appointment`  
  - Timer (visible when busy)
- Queue order = next-in-line priority.
- Manual drag-and-drop reorder supported.
- Automatic reorder when status changes.

### 3.2 Drag & Drop
- Pointer Events implementation works on desktop and mobile.
- Dragging is limited to the **handle** area.
- Polling pauses during drag, resumes after drop.
- Only one polling interval active at any time.

### 3.3 Status Flip Interaction
- Clicking a status label **flips the card** in 3D to reveal back face.
- Back shows a white background with:
  - **Title:** “Update Status” (black, responsive)
  - **Buttons:** `Available`, `Busy – Walk-In`, `Busy – Appointment`
- Selecting a status posts to `/api/barbers.php?action=status`, applies rotation rules, and flips back.
- Card maintains same dimensions; flip doesn’t change layout.
- Dragging closes any flipped card automatically.

### 3.4 Roles

| Role | Permissions |
|------|--------------|
| **Owner** | Full control: manage users, barbers, settings, and TV token. |
| **Admin** | Manage users and daily operations (barbers, settings) but cannot remove final owner. |
| **Front-Desk** | Toggle statuses, reorder queue, access TV mode. |
| **Viewer** | Read-only access to queue and TV mode. |

### 3.5 Admin User Management
- Dedicated **Users** page (admin/owner only) reachable from site navigation.
- Responsive layout with search-as-needed list on the left and CRUD form on the right.
- Supports create, read, update, delete for local users with role controls and password resets.
- Enforces role safeguards (must keep ≥1 owner and ≥1 admin/owner).
- Search matches name, email, or username; results update without leaving the page.

### 3.6 Authentication
- OAuth via **Google** and **Apple** for general users.  
- Local username/password login for Owner/Admin bootstrap.  
- Sessions persist until logout or browser close.  
- **TV Mode:** read-only via `/tv?token=XYZ`, token validated from DB (independent of session).

### 3.7 Logout & Cache Clear
- Logout destroys PHP session and sends anti-cache headers.  
- Redirects to `/logout_clear.html` which:
  - Clears localStorage, sessionStorage, and Cache API.  
  - Redirects to `/login?v=<timestamp>` (cache-busting).  
- TV token unaffected (stored in DB, validated by URL).

### 3.8 Display & Responsiveness
- Fully responsive (mobile, desktop, TV).  
- **TV Mode:** full-screen auto-refresh (2–5s).  
- Light/dark themes configurable.  
- 3D flip and drag tested on Chrome, Safari, iOS.

---

## 4. Functional Requirements

| ID | Requirement | Priority |
|----|--------------|----------|
| FR-1 | Display list of barbers with name, status, timer. | High |
| FR-2 | Tap to flip card and update status. | High |
| FR-3 | Drag-and-drop reorder with Pointer Events. | High |
| FR-4 | Auto-refresh queue every 2–5s. | High |
| FR-5 | Google/Apple OAuth login + local login. | High |
| FR-6 | Admin can edit title/logo/theme. | Medium |
| FR-7 | Public TV view via token URL. | High |
| FR-8 | Persist queue order and status. | High |
| FR-9 | Logout clears cache and session. | High |
| FR-10 | TV token independent of session logout. | High |

---

## 5. Non-Functional Requirements

| Category | Specification |
|-----------|---------------|
| **Performance** | Status change visible within 2–5s across screens. |
| **Scalability** | Single shop ≤ 12 barbers. |
| **Security** | OAuth + local auth; secure session; TV token randomized. |
| **Deployment** | Works on Apache/PHP 8.3 + MySQL via FTP/SSH. |
| **Simplicity** | Plain PHP, minimal JS libs. |
| **Responsiveness** | Flex/Grid + CSS clamp units. |
| **Polling Interval** | 2–5s AJAX. |

---

## 6. Screens

### 6.1 Login
- Username/password or Google/Apple buttons.  
- Redirect to queue on success.

### 6.2 Queue
- Title, drag handle, barber name, status badge, timer.
- Flip animation on status label → update status on back.
- Manual drag-and-drop reorder.
- Responsive spacing between name, position, and timer.

### 6.3 Settings
- Owner/Admin only.  
- Update shop title, logo, theme.  
- Regenerate TV token.

### 6.4 Users
- Admin/Owner only.  
- Search users by name, email, or username.  
- Inline form for create/update, including role selection and password reset.  
- Delete protected by confirmation; enforces at least one admin/owner remains.

### 6.5 TV Mode
- Access `/tv?token=XYZ`  
- Read-only vertical queue, large typography, color-coded statuses.

---

## 7. Color Coding

| Status | Color |
|---------|-------|
| Available | Green `#18a558` |
| Busy – Walk-In | Red `#d93025` |
| Busy – Appointment | Orange `#ff8c00` |
| Inactive/All Busy | Gray `#9ca3af` |

---

## 8. Database Schema (simplified)

### `users`
| Field | Type | Notes |
|--------|------|-------|
| id | INT PK |  |
| oauth_provider | ENUM('google','apple','local') | Auth provider |
| oauth_id | VARCHAR(255) | Unique per provider (nullable for local) |
| email | VARCHAR(255) | Unique |
| name | VARCHAR(255) | Display name |
| username | VARCHAR(100) | Optional local login |
| password_hash | VARCHAR(255) | Argon2id (local only) |
| role | ENUM('viewer','frontdesk','admin','owner') | Authorization tier |
| last_login_at | DATETIME | Tracks most recent login |
| created_at / updated_at | TIMESTAMP | Audit fields |

### `barbers`
| Field | Type | Notes |
|--------|------|-------|
| id | INT PK |  |
| name | VARCHAR(100) |  |
| status | ENUM('available','busy_walkin','busy_appointment') |  |
| position | INT | Queue order |
| timer_start | DATETIME | Busy start time |
| updated_at | TIMESTAMP | Auto-update |

### `settings`
| Field | Type | Notes |
|--------|------|-------|
| id | INT PK |  |
| title | VARCHAR(100) | Shop title |
| logo_url | VARCHAR(255) | Optional |
| theme | VARCHAR(20) | light/dark |
| tv_token | VARCHAR(64) | TV access key |

---

## 9. API Endpoints

| Method | Endpoint | Description |
|--------|-----------|-------------|
| GET | `/api/barbers.php?action=list` | Return ordered barbers. |
| POST | `/api/barbers.php?action=status` | Update status + rotation logic. |
| POST | `/api/barbers.php?action=order` | Manual reorder. |
| GET | `/api/settings.php?action=get` | Get settings. |
| POST | `/api/settings.php?action=save` | Save settings. |
| GET | `/tv?token=XYZ` | TV mode read-only. |
| GET | `/api/users.php?action=list&[q]` | Admin list/search users. |
| GET | `/api/users.php?action=get&user_id=ID` | Admin fetch single user. |
| POST | `/api/users.php?action=create` | Admin create local user. |
| POST | `/api/users.php?action=update` | Admin update user details/role. |
| POST | `/api/users.php?action=delete` | Admin delete user (role safeguards). |

---

## 10. Queue Logic

| Transition | Behavior |
|-------------|-----------|
| Available → Busy – Walk-In | Move to bottom, start timer. |
| Busy – Walk-In → Busy – Appointment | Stay, restart timer. |
| Busy – Appointment → Available | Stay, clear timer. |

Polling pauses during drag or flip, resumes after completion.

---

## 11. UI/UX Guidelines

- Minimal, flat card design with consistent padding.  
- 3D flip animation: 300 ms ease, smooth transition.  
- Timer in top-right corner (mm:ss).  
- Sticky header with logo/title.  
- Fully responsive using CSS `clamp()` and `gap`.  
- No hover dependencies; all actions tap-friendly.

---

## 12. Security & Logout Behavior

- All authenticated pages send `Cache-Control: no-store, no-cache`.  
- `auth/logout.php` clears session and redirects to `/logout_clear.html`.  
- `/logout_clear.html` clears browser storage and redirects to `/login` with timestamp.  
- TV mode uses `settings.tv_token` validation (no session dependency).  
- Protected `main` branch — all updates via PR review.

---

## 13. Development Environment

### Local (Docker)
- PHP 8.3 + Apache  
- MySQL 8  
- Containerized via `docker-compose.yml`  
- Auto-reload on code changes

### Deployment
- Upload via FTP or SSH to DreamHost VPS root.  
- Import `/sql/database.sql`.  
- Update `includes/config.php` with DB creds and OAuth keys.  
- Ensure `.htaccess` handles rewrites.

---

## 14. Version Control / Git Workflow
- `main` branch protected (PR-only).  
- Feature branches per task (`feat/*`, `fix/*`).  
- Code reviewed and merged via GitHub PR.  
- Codex assistant configured to show diffs before commit.

---

## 15. Acceptance Criteria
- Queue updates propagate ≤ 5 s.  
- Drag-and-drop and flip work on desktop and mobile.  
- Logout clears all cached data; TV remains active.  
- Layout matches stable reference (front and flipped).  
- Fully functional offline-resilient UI in production.
- Admin navigation displays Users/Barbers menu options when role ≥ admin, and the Users page supports end-to-end CRUD with role safeguards.

---

## 16. Future Enhancements
- Multi-shop (location) support.  
- Customer name/service tracking per walk-in.  
- Analytics dashboard (avg wait per barber).  
- Push or PWA notifications when next barber is ready.

---

**End of Document – v1.4**
