# Barber Queue Management App (PRD) v1.3

**Environment:**

- Development: Docker (PHP 8.3 + Apache + MySQL)
- Production: DreamHost VPS (Apache + PHP 8.3 + MySQL)

**Deployment:** Root entry (no public subfolder), containerized dev environment, FTP/SSH for prod.  
**Platform:** Web (responsive for phone, desktop, TV)

---

## 1. Overview

The Barber Queue App manages **walk-in flow** in a barber shop. When a customer arrives without an appointment, the next **available barber** is automatically assigned according to a live rotation queue.

The system is designed for:

- **Front-Desk Operators** managing walk-ins.
- **Owners/Admins** configuring shop details.
- **TV Displays** showing real-time barber status.

---

## 2. Objectives

1. Provide a **fair, transparent rotation** among barbers.
2. Enable **quick status updates** with one tap.
3. Support **responsive display** across devices and TV.
4. Use a **lightweight PHP + MySQL** stack deployable via FTP/SSH or containerized for development.
5. Support both **OAuth (Google/Apple)** and **local username/password** authentication.
6. Enable **root-entry deployment** without a public subfolder for easier hosting.

---

## 3. Core Features

### 3.1 Queue System

- Each barber appears as a **card** showing:
  - Name
  - Status: `Available`, `Busy - Walk-In`, or `Busy - Appointment`
  - Timer (visible when busy)
- Queue order represents next-in-line priority.
- Automatic and manual reordering supported.

### 3.2 Status Rules

| Action                 | Queue Behavior                                             |
| ---------------------- | ---------------------------------------------------------- |
| **Busy - Walk-In**     | Move card immediately to **bottom** of queue. Start timer. |
| **Busy - Appointment** | Stay **at top**, start timer, continue normal rotation.    |
| **Available**          | Keep current position, clear timer.                        |
| **All Busy**           | Show gray cards with “All Busy” message.                   |

### 3.3 Timer

- When any “Busy” status is set, a **timer starts** in the corner of the card.
- Resets when status returns to “Available.”

### 3.4 Roles & Permissions

| Role            | Permissions                                                                 |
| --------------- | --------------------------------------------------------------------------- |
| **Owner/Admin** | Manage barbers, update title/theme/logo, regenerate TV token, manage users. |
| **Front-Desk**  | Toggle statuses, reorder queue, view TV mode.                               |

### 3.5 Authentication

- OAuth via **Google** and **Apple**.
- Local login via **username/password** with secure password hashing.
- Sessions persist until logout or browser close.
- **TV Mode** uses a tokenized public URL with read-only access.

### 3.6 Display & Responsiveness

- Fully responsive (mobile, desktop, TV).
- **TV Mode:** full-screen, auto-refresh (2–5 seconds).
- Theme: configurable light/dark mode.

---

## 4. Functional Requirements

| ID    | Requirement                                         | Priority |
| ----- | --------------------------------------------------- | -------- |
| FR-1  | Display list of barbers with name and status.       | High     |
| FR-2  | Toggle barber status by tapping or clicking.        | High     |
| FR-3  | Move cards according to status rules.               | High     |
| FR-4  | Auto-refresh every 2–5 seconds (polling).           | High     |
| FR-5  | Google/Apple OAuth login plus local login.          | High     |
| FR-6  | Admin can edit title/logo/theme and manage users.   | Medium   |
| FR-7  | Public TV view using token URL.                     | High     |
| FR-8  | Save queue state and persist on reload.             | High     |
| FR-9  | Store all data in MySQL (barbers, users, settings). | High     |
| FR-10 | Support containerized development environment.      | Medium   |

---

## 5. Non-Functional Requirements

| Category             | Specification                                                                   |
| -------------------- | ------------------------------------------------------------------------------- |
| **Performance**      | Status change visible across screens within 2–5s.                               |
| **Scalability**      | Single shop, ≤ 12 barbers.                                                      |
| **Security**         | OAuth + local login with password hashing; secure session; TV token randomized. |
| **Deployment**       | Works on Apache/PHP 8.3 + MySQL via FTP/SSH; Docker for dev.                    |
| **Stack Simplicity** | Plain PHP, minimal libraries.                                                   |
| **Responsiveness**   | Bootstrap or TailwindCSS.                                                       |
| **Polling Interval** | 2–5 seconds using AJAX.                                                         |

---

## 6. Screens

### 6.1 Login

- Buttons: “Sign in with Google” / “Sign in with Apple”
- Local login form: username + password
- Redirect to Main Queue after success.

### 6.2 Queue Screen

- Configurable title (from settings).
- Cards for each barber with name + status + timer.
- Tap = cycle status (`Available → Busy - Walk-In → Busy - Appointment → Available`).
- Auto reorder on server.
- Visible to both roles.

### 6.3 Settings (Admin Only)

- Change shop title, logo, theme.
- Add/remove/edit barbers.
- Regenerate TV token.
- Manage users (add/edit roles, reset passwords).

### 6.4 TV Mode

- Access: `/tv?token=XYZ`
- Read-only view of queue.
- Large fonts, color coded statuses.
- Auto-refresh 2–5s.

---

## 7. Color Coding

| Status              | Color              |
| ------------------- | ------------------ |
| Available           | Green (`#18a558`)  |
| Busy - Walk-In      | Red (`#d93025`)    |
| Busy - Appointment  | Orange (`#ff8c00`) |
| Inactive / All Busy | Gray (`#9ca3af`)   |

---

## 8. Database Schema Updates

### `users`

| Field          | Type                      | Notes                                                     |
| -------------- | ------------------------- | --------------------------------------------------------- |
| id             | INT, PK                   |                                                           |
| oauth_provider | VARCHAR(20)               | google/apple/null                                         |
| oauth_id       | VARCHAR(100)              | unique external ID or NULL for local users                |
| username       | VARCHAR(50)               | unique for local login; nullable for OAuth users          |
| password_hash  | VARCHAR(255)              | hashed password for local login; nullable for OAuth users |
| name           | VARCHAR(100)              | user name                                                 |
| role           | ENUM('owner','frontdesk') |                                                           |
| created_at     | TIMESTAMP                 |                                                           |

### `barbers`

| Field       | Type                                               | Notes           |
| ----------- | -------------------------------------------------- | --------------- |
| id          | INT, PK                                            |                 |
| name        | VARCHAR(100)                                       |                 |
| status      | ENUM('available','busy_walkin','busy_appointment') |                 |
| position    | INT                                                | queue order     |
| timer_start | DATETIME                                           | busy start time |
| updated_at  | TIMESTAMP                                          | auto-update     |

### `settings`

| Field    | Type         | Notes                |
| -------- | ------------ | -------------------- |
| id       | INT, PK      |                      |
| title    | VARCHAR(100) | display title        |
| logo_url | VARCHAR(255) | optional             |
| theme    | VARCHAR(20)  | light/dark           |
| tv_token | VARCHAR(64)  | read-only access key |

---

## 9. API Endpoints

| Method | Endpoint                         | Description                                  |
| ------ | -------------------------------- | -------------------------------------------- |
| GET    | `/api/barbers.php?action=list`   | Return ordered list of barbers.              |
| POST   | `/api/barbers.php?action=status` | Update barber status (apply rotation logic). |
| POST   | `/api/barbers.php?action=order`  | Manual reorder (by user drag/drop).          |
| GET    | `/api/settings.php?action=get`   | Get app title/logo/theme.                    |
| POST   | `/api/settings.php?action=save`  | Update configuration (admin).                |
| GET    | `/api/users.php?action=list`     | List users (admin only).                     |
| POST   | `/api/users.php?action=save`     | Add/edit user (admin only).                  |
| POST   | `/api/users.php?action=delete`   | Delete user (admin only).                    |
| GET    | `/tv?token=XYZ`                  | TV read-only view.                           |

---

## 10. Queue Logic

### Transitions

| From → To                       | Behavior                           |
| ------------------------------- | ---------------------------------- |
| Available → Busy-Walk-In        | Move card to bottom, start timer.  |
| Busy-Walk-In → Busy-Appointment | Stay same position, restart timer. |
| Busy-Appointment → Available    | Stay same position, clear timer.   |

### Manual Rotation

- Any logged-in user may reorder cards manually.
- API normalizes `position` values (1..N).

### Auto-Refresh

- Clients poll `/api/barbers.php?action=list` every 3s (configurable).
- Server returns JSON list with updated order and timers.

---

## 11. UI / UX Guidelines

- Minimal card layout with clear colors and text.
- Smooth CSS transition when status or order changes.
- Timer in top-right corner (mm:ss elapsed).
- Sticky header with title and small logo.
- TV mode: fullscreen gridless layout with large typography.

---

## 12. Directory Structure

/barber-queue-app/  
├─ assets/  
│ ├─ css/  
│ │ ├─ base.css  
│ │ └─ tv.css  
│ ├─ js/  
│ │ ├─ app.js  
│ │ ├─ tv.js  
│ │ └─ auth.js  
│ └─ img/  
│ └─ logo.png  
├─ views/  
│ ├─ login.php  
│ ├─ queue.php  
│ ├─ settings.php  
│ ├─ tv.php  
│ ├─ \_layout_header.php  
│ └─ \_layout_footer.php  
├─ api/  
│ ├─ barbers.php  
│ ├─ settings.php  
│ ├─ users.php  
│ ├─ auth_check.php  
│ └─ index.php  
├─ auth/  
│ ├─ google_start.php  
│ ├─ google_callback.php  
│ ├─ apple_start.php  
│ ├─ apple_callback.php  
│ ├─ local_login.php  
│ └─ logout.php  
├─ includes/  
│ ├─ config.php  
│ ├─ db.php  
│ ├─ session.php  
│ ├─ auth.php  
│ ├─ queue_logic.php  
│ ├─ barber_model.php  
│ ├─ settings_model.php  
│ ├─ user_model.php  
│ └─ security.php  
├─ sql/  
│ └─ database.sql  
├─ Dockerfile  
├─ docker-compose.yml  
├─ README.md  
└─ LICENSE

---

## 13. Non-UI Scripts

### Polling Logic (`assets/js/app.js`)

- Fetch `/api/barbers.php?action=list` every 3s.
- Update DOM without full reload.
- On click, POST new status → re-render queue.

### TV Script (`assets/js/tv.js`)

- Fetch `/api/barbers.php?action=list&token=XYZ` every 3s.
- Render read-only queue.
- No interactive actions.

---

## 14. Deployment & Development

### 14.1 Production (DreamHost VPS)

1. Create MySQL DB and user in DreamHost panel.
2. Upload files via FTP or SSH to root directory (no public subfolder).
3. Import `/sql/database.sql`.
4. Configure `includes/config.php` with DB and OAuth keys.
5. Set document root to app root.
6. Test `/login` and `/queue`.
7. Configure `/settings` for title/logo/theme.
8. Use `/tv?token=YOUR_TOKEN` for TV display.

### 14.2 Development (Docker)

- Use provided `Dockerfile` and `docker-compose.yml` for local dev environment.
- Run `docker-compose up` to start PHP, Apache, and MySQL containers.
- Access app at `http://localhost`.
- Supports hot reload and debugging.

---

## 15. Acceptance Criteria

- Queue updates propagate within 5 seconds across all screens.
- Busy-Walk-In moves to bottom; Busy-Appointment stays top.
- Timer appears correctly for busy barbers.
- Statuses persist across reloads.
- TV mode works via token without login.
- Google/Apple sign-in and local login redirect correctly.
- Responsive design scales properly across devices.
- Admin can manage users, barbers, and settings.
- Containerized dev environment works as expected.

---

## 16. Future Enhancements

- Customer name/service capture for walk-ins.
- Multi-location support.
- Analytics dashboard (cuts per barber, average wait).
- Push notifications for “next barber ready.”
- Improved accessibility features.

---

**End of Document**
