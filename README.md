# 📦 PRODUCT INBOUND SHIPMENT COUNTING RECORD

A **multi-user warehouse inbound counting** system built with **PHP 8**, **MySQL**, and a lightweight custom admin/user UI — admin-managed shipments, warehouse counting records, status matching (qty match / mismatch), optional photos, SMTP password reset, and idle-session logout for XAMPP / cPanel.

---

## ✨ Features

- 🔐 **Role-based Login**: Admin vs warehouse user sessions, password hashing, 5-minute inactivity logout.
- 🔑 **Forgot Password**: Email **6-digit verification code** via SMTP (Gmail / QQ / 163 / cPanel mail); rate-limited, single-use, expiry.
- 📊 **Admin Dashboard**: Shortcuts + quick stats (shipments, counting records, users, today counts).
- 🚚 **Inbound Shipments CRUD**: Inbound date, product name, unique shipment number, total cartons, total quantity, optional photo.
- 🧮 **User Counting Records**: Select shipment → auto-fill product → enter start/end time + counted qty + remarks; edit/delete own records only.
- 🟢🔴🔵🟠 **Status Indicators**: Counted / not counted / qty matches admin / qty mismatch.
- 🔍 **Search & Filters**: Product name, shipment number; today / daily date / past 7 days.
- 👥 **User Management**: Admin creates / edits / deletes warehouse accounts (public self-registration discouraged).
- 👤 **User Profile**: View account info and change password.
- 📎 **Photo Support**: Optional photo upload for shipments / counting (stored under `uploads/photos`).
- 🛡️ **Security**: PDO prepared statements, `htmlspecialchars()` output, role gates, own-record scoping, session timeout.

---

## 🏗️ Tech Stack

| Category | Technology |
| --- | --- |
| 🖥️ Frontend | HTML5 / CSS3 · vanilla JS (modals, filters, photo, inactivity) |
| 🔙 Backend | PHP 8+ · Apache (XAMPP / cPanel) |
| 🗄️ Database | MySQL — `users`, `admin_shipments`, `user_count_records` |
| ✉️ Email | SMTP (`config/mail.php`) or log driver for local dev |
| 🔗 Architecture | Server-rendered PHP admin/user panels + thin action endpoints |
| 🛠️ Local Dev | XAMPP (Apache + MySQL) |

---

## 🚀 Quick Start

### 1. Requirements

- **XAMPP** (Apache + MySQL) on Windows 10/11, or PHP + MySQL on cPanel / Linux
- PHP **8.0+** with PDO MySQL, OpenSSL (for SMTP TLS/SSL)
- Optional: SMTP mailbox for real forgot-password emails

### 2. Install (local XAMPP)

1. Place the project, e.g. `C:\xampp\htdocs\inbound_counting`

2. Start **Apache** and **MySQL** in the XAMPP Control Panel.

3. Configure database credentials in `config/database.php` (copy from `config/database.example.php` if needed):

   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'inbound_counting');
   define('SESSION_TIMEOUT', 300); // seconds — 5 min idle logout
   ```

4. Create tables + default admin (recommended):

   Open [http://localhost/inbound_counting/install.php](http://localhost/inbound_counting/install.php)

   Or import SQL manually:

   ```powershell
   cd C:\xampp\htdocs\inbound_counting
   mysql -u root < sql\schema.sql
   ```

   Then open `install.php` once so the default admin row is created (or insert admin yourself).

5. Open **http://localhost/inbound_counting/login.php**

6. Default admin (change immediately):

   ```text
   Username: admin
   Password: admin123
   ```

7. (Optional) Configure SMTP in `config/mail.php` (copy from `config/mail.example.php`) so forgot-password emails reach real inboxes.

8. On production: **delete `install.php`** after setup.

---

## 🎬 Project Walkthrough

Click the preview image below to watch the full system walkthrough on Google Drive:

<!-- TODO: replace href with your video URL -->
<a href="https://drive.google.com/file/d/REPLACE_WITH_YOUR_VIDEO_ID/view?usp=drive_link" target="_blank">
  <img src="docs/screenshots/dashboard.png" alt="Watch Video Walkthrough" width="100%" style="border: 1px solid #e1e4e8;" />
</a>

---

## 🖼️ Project Screenshots

<table border="0">
  <tr>
    <td width="50%">
      <img src="docs/screenshots/login.png" alt="Login" width="100%" style="border: 1px solid #e1e4e8;" />
    </td>
    <td width="50%">
      <img src="docs/screenshots/dashboard.png" alt="Admin Dashboard" width="100%" style="border: 1px solid #e1e4e8;" />
    </td>
  </tr>
  <tr>
    <td width="50%">
      <img src="docs/screenshots/shipments.png" alt="Inbound Shipments" width="100%" style="border: 1px solid #e1e4e8;" />
    </td>
    <td width="50%">
      <img src="docs/screenshots/overview.png" alt="User Counting Records" width="100%" style="border: 1px solid #e1e4e8;" />
    </td>
  </tr>
  <tr>
    <td width="50%">
      <img src="docs/screenshots/users.png" alt="User Management" width="100%" style="border: 1px solid #e1e4e8;" />
    </td>
    <td width="50%">
      <img src="docs/screenshots/user-dashboard.png" alt="Warehouse Counting Dashboard" width="100%" style="border: 1px solid #e1e4e8;" />
    </td>
  </tr>
  <tr>
    <td width="50%">
      <img src="docs/screenshots/count-modal.png" alt="Add Counting Record" width="100%" style="border: 1px solid #e1e4e8;" />
    </td>
    <td width="50%">
      <img src="docs/screenshots/forgot-password.png" alt="Forgot Password" width="100%" style="border: 1px solid #e1e4e8;" />
    </td>
  </tr>
</table>

> Place PNG screenshots under `docs/screenshots/` using the filenames above (or update the `src` paths).

---

## 🎬 How It Works

1. **Admin creates warehouse users** — accounts are managed under **User Management** (not meant for public signup).
2. **Admin adds inbound shipments** — shipment number, product, cartons, expected total qty (and optional photo).
3. **Warehouse user logs in** — sees available (uncounted) shipments and their own counting history.
4. **User submits a count** — picks shipment number (product/cartons auto-filled), enters start/completion time, counted quantity, optional remarks.
5. **Status updates** — admin overview shows 🟢 counted, 🔴 not counted, 🔵 qty matches, 🟠 qty mismatch.
6. **Idle logout** — after `SESSION_TIMEOUT` seconds without activity, the session expires and the user must log in again.

The browser only talks to your PHP app. Email delivery (password reset) happens server-side via SMTP when configured.

---

## 🔌 Pages

Base: `http://localhost/inbound_counting`

| Method | Page | Description |
| --- | --- | --- |
| `GET` | `/` | Redirect to dashboard or login |
| `GET` / `POST` | `/login.php` | Admin / user login |
| `GET` / `POST` | `/forgot_password.php` | Request + verify 6-digit email code |
| `GET` / `POST` | `/reset_password.php` | Legacy / token-based reset (if used) |
| `GET` / `POST` | `/register.php` | Optional self-register (prefer admin-created users) |
| `GET` | `/logout.php` | End session |
| `GET` | `/install.php` | One-time DB install + default admin |
| `GET` | `/admin/dashboard.php` | Admin home + quick stats |
| `GET` / `POST` | `/admin/shipments.php` + `shipment_action.php` | Inbound shipments CRUD |
| `GET` | `/admin/overview.php` | All user counting records + status |
| `GET` / `POST` | `/admin/users.php` + `user_action.php` | Warehouse user CRUD |
| `GET` / `POST` | `/user/dashboard.php` + `count_action.php` | My counting records |
| `GET` / `POST` | `/user/profile.php` + `profile_action.php` | Profile + change password |

### Admin shipment create flow (shape)

```
POST admin/shipment_action.php
  inbound_date + product_name + shipment_number + total_carton + total_quantity (+ photo)
  → valid   → insert/update admin_shipments
  → invalid → flash error (empty fields / duplicate shipment number)
```

### User counting flow (shape)

```
POST user/count_action.php
  admin_shipment_id + counting_date + start_time + completion_time + total_quantity (+ remarks)
  → valid   → insert/update user_count_records (own user_id only)
  → invalid → flash error (missing shipment / times / already counted)
```

### Status match flow

```
admin shipment total_quantity
  × user_count_records SUM(total_quantity) for same shipment_number
  → no user rows     → 🔴 not counted
  → sum == admin qty → 🟢 counted + 🔵 match
  → sum != admin qty → 🟢 counted + 🟠 mismatch
```

---

## 📐 Domain Rules

- **Shipment number** is unique on `admin_shipments`.
- Each inbound shipment should be counted **once** (duplicate `admin_shipment_id` counting is rejected).
- Users may **edit / delete only their own** counting records.
- Product name (and carton display) are taken from the selected admin shipment.
- Counting date defaults to today on create when left empty.
- Start time and completion time are required.
- Status dots compare **sum of counted qty** vs **admin expected total quantity**.
- Public registration exists but production should rely on **admin-created** warehouse users.
- UI language is English; layout is custom CSS admin/user panels (sidebar + content).

---

## 📁 Project Structure

```text
inbound_counting/
├── admin/
│   ├── dashboard.php          # Admin home + stats
│   ├── shipments.php          # Inbound shipments list / modal form
│   ├── shipment_action.php    # Create / update / delete shipments
│   ├── overview.php           # All user counting records
│   ├── users.php              # Warehouse user management
│   └── user_action.php        # Create / update / delete users
├── user/
│   ├── dashboard.php          # My counting records + available shipments
│   ├── count_action.php       # Create / update / delete counts
│   ├── profile.php            # Profile view
│   └── profile_action.php     # Change password
├── api/
│   └── ping.php               # Lightweight session / health helper
├── assets/js/
│   ├── auth.js
│   ├── dashboard.js
│   ├── admin-modal.js
│   ├── photo.js
│   └── inactivity.js          # Idle timeout UX
├── includes/
│   ├── auth.php               # Session, roles, login helpers
│   ├── admin_layout.php / user_layout.php / site_footer.php
│   ├── helpers.php
│   ├── status.php             # Status dots + match logic
│   ├── mail.php / smtp.php    # Password-reset mail
│   ├── password_reset.php
│   ├── upload_photo.php
│   └── migrate.php            # Safe schema upgrades
├── config/
│   ├── database.php           # DB + BASE_URL + session timeout
│   ├── database.example.php
│   ├── mail.php               # SMTP settings (do not commit secrets)
│   └── mail.example.php
├── sql/
│   ├── schema.sql
│   ├── seed_data.sql
│   └── migrate_from_old.sql
├── uploads/photos/            # Uploaded images
├── storage/logs/              # mail.log when MAIL_DRIVER=log
├── docs/
│   └── screenshots/           # README UI screenshots
├── install.php
├── login.php
├── logout.php
├── forgot_password.php
├── register.php
└── README.md
```

---

## ✉️ SMTP / Forgot Password Setup

1. Copy `config/mail.example.php` → `config/mail.php`.
2. Set `MAIL_DRIVER` = **`smtp`**.
3. Fill `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USER`, `SMTP_PASS`, `MAIL_FROM`.

| Provider | Host | Port | Secure | Password tip |
| --- | --- | --- | --- | --- |
| Gmail | `smtp.gmail.com` | `587` | `tls` | Google **App password** (not login password) |
| QQ Mail | `smtp.qq.com` | `465` | `ssl` | SMTP **authorization code** |
| 163 | `smtp.163.com` | `465` | `ssl` | SMTP **authorization code** |
| cPanel | `mail.yourdomain.com` | `587` | `tls` | Mailbox password |

**Offline / local only:** `MAIL_DRIVER=log` → codes written to `storage/logs/mail.log`.

**User flow:** enter email → receive **6-digit code** → enter code + new password on the same page (phone-friendly; no magic link required).

---

## 🗄️ Database

MySQL database: `inbound_counting`  
Charset: `utf8mb4` / `utf8mb4_unicode_ci`

### Core tables

| Table | Purpose |
| --- | --- |
| `users` | Admin + warehouse accounts (`role`, email, password hash, reset fields) |
| `admin_shipments` | Inbound shipment master data (date, product, shipment #, cartons, qty, photo) |
| `user_count_records` | Warehouse counting submissions linked to shipment + user |

Status meaning (UI, not a DB enum):

| Dot | Meaning |
| --- | --- |
| 🟢 | Counted |
| 🔴 | Not counted |
| 🔵 | Counted qty matches admin total |
| 🟠 | Counted qty mismatch vs admin total |

---

## ⚠️ Error Handling

Friendly / flashed handling for:

- Invalid login credentials  
- Session timeout (redirect with `?timeout=1`)  
- Empty / invalid shipment or counting fields  
- Duplicate shipment number  
- Shipment already counted  
- Users editing another user’s record (blocked by `user_id` scope)  
- SMTP / mail failures on forgot password  
- Database connection failure  

---

## 🔐 Security Notes

- All SQL uses **PDO prepared statements**.
- Passwords use **`password_hash()` / `password_verify()`**.
- Admin pages require **`requireAdmin()`**; user pages require **`requireUserRole()`**.
- Counting mutations are scoped to the logged-in **`user_id`**.
- Output is escaped with **`htmlspecialchars()`**.
- Idle sessions expire after **`SESSION_TIMEOUT`** (default 300s).
- Password reset: rate limit, expiry (`PASSWORD_RESET_EXPIRY`), single-use codes.
- Keep `config/database.php` and `config/mail.php` secrets private — do not commit real SMTP / DB passwords.
- Change the default `admin` / `admin123` password immediately after install.
- Delete **`install.php`** on production after first setup.

---

## ✅ Production Checklist

- [ ] Change the default admin password  
- [ ] Delete `install.php`  
- [ ] Set real MySQL credentials in `config/database.php`  
- [ ] Confirm `BASE_URL` resolves correctly under your document root  
- [ ] Configure SMTP in `config/mail.php` and test forgot password  
- [ ] Confirm PHP OpenSSL / cURL as required by your host for SMTP  
- [ ] Create warehouse users from **User Management**  
- [ ] Add a test inbound shipment and one counting record before go-live  
- [ ] Ensure `uploads/photos` is writable by the web server  
- [ ] Add screenshots under `docs/screenshots/` and replace the walkthrough video link  

---

## 🧪 Suggested Local Smoke Test

1. Install → login as `admin` / `admin123`  
2. Create a warehouse user  
3. Add an inbound shipment (unique shipment number + total quantity)  
4. Log out → log in as the warehouse user  
5. Submit a counting record for that shipment  
6. As admin, open **User Counting Records** and confirm status dots  
7. Trigger forgot password with SMTP (or `MAIL_DRIVER=log`) and reset once  
