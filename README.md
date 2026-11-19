# Student Registration & Information Management System

A web application for school conferences. Students **scan a QR code → fill out the form → review → submit → get a confirmation**.
Organizers manage conferences, QR codes, participants, attendance and reports from an admin dashboard.

**Built with:** PHP 8 (no framework) · MySQL / MariaDB · HTML, CSS, JavaScript.
All JavaScript libraries (Chart.js, QR code, Excel/PDF export, camera scanner, icons) are included in `assets/vendor`,
so no build step, Composer or npm is needed. Just copy the files.

---

## Features

**Student portal (mobile-first)**
- Scan QR → registration form for that conference (conference info is filled in automatically)
- Sections: Personal, Academic, Conference Participant Information. Fields change depending on Student / Faculty / Guest
- Validation for Student ID format, PH mobile numbers and duplicate registrations
- Review page with **Edit** before submitting
- Confirmation with a unique **reference number**, conference details, a personal **attendance QR code**, and print
- "Find my registration" page (reference no. + ID / contact number)

**Admin dashboard**
- Dashboard: stat cards + charts (course, year level, school, status, 30-day trend)
- Conference Management: create / edit / view, schedule, registration open & close dates, participant limit, enable/disable, archive
- Participant Records: search, 9 filters, sorting, pagination, bulk actions, view / edit / confirm / pending / cancel / archive, export (Excel, PDF, CSV)
- Participant profile: 6 organized sections, print / export
- QR Code Management: preview, download PNG, printable A4 poster, copy link, activate/deactivate, regenerate, scan & registration counts
- Attendance Monitoring: camera QR scanning or manual search, duplicate check-in prevention, live counts, undo, attendance reports
- Reports & Analytics: per-conference summary, by school / course / year / type / status, registration history; export to Excel & PDF, print
- Activity Logs, Admin Accounts (Super Admin / Conference Admin), System Settings (lists, Student ID format, database backup)

**Security:** password hashing (bcrypt), login lockout after 5 failed attempts, CSRF protection on every form, prepared statements (no SQL injection),
output escaping (no XSS), role-based access, forced password change for new accounts, session timeout, `.htaccess` protection of config / internal folders.
Registration QR codes contain only a random token; attendance QR codes are separate.

---

## 1. Run it on your laptop (XAMPP + MySQL Workbench)

### Step 1: Install
- [XAMPP](https://www.apachefriends.org/) (includes Apache + PHP + MySQL/MariaDB)
- MySQL Workbench (you already have it)

Open the **XAMPP Control Panel** and click **Start** for **Apache** and **MySQL**.

> If MySQL Workbench uses its own MySQL Server (MySQL Installer) on port 3306, don't also start XAMPP's MySQL.
> Use one or the other, then put its username/password in `config.php`.

### Step 2: Copy the project
Copy this whole folder to `C:\xampp\htdocs\bstm` (any folder name works).

### Step 3: Create the database in MySQL Workbench
1. Open Workbench → connect to **Local instance** (`localhost:3306`, user `root`; XAMPP's root password is empty).
2. Run:
   ```sql
   CREATE DATABASE studentregister CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE studentregister;
   ```
3. **File → Open SQL Script…** → `database/schema.sql` → click the ⚡ (Execute) button.
4. *(Optional, for testing)* open `database/sample_data.sql` → Execute. This adds 3 sample conferences and about 130 sample participants.
   **Do not import the sample data on the live server.**

### Step 4: Check `config.php`
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'studentregister');
define('DB_USER', 'root');
define('DB_PASS', '');        // your MySQL password if you set one
```

### Step 5: Open it
- Student portal: <http://localhost/bstm/>
- Admin: <http://localhost/bstm/admin/>
- Default login: **`admin` / `Admin@123`**. You will be asked to change the password right away.

### Step 6: Test the QR code with your phone
`localhost` only works on the laptop itself, so a QR code containing `localhost` can't be opened by a phone.

1. Connect your phone and laptop to the **same Wi-Fi**.
2. Find your laptop's IP address: open Command Prompt → `ipconfig` → look for **IPv4 Address** (e.g. `192.168.1.5`).
3. Open the admin from the laptop using that IP: `http://192.168.1.5/bstm/admin/`.
   The QR codes will then contain that address.
   *(Or set **System Settings → Public Base URL** to `http://192.168.1.5/bstm`.)*
4. Go to **QR Code Management** and scan the QR code with your phone camera.
5. If the phone can't open the page, allow Apache through Windows Firewall
   (Windows Security → Firewall → Allow an app → `Apache HTTP Server`, Private networks).

> **Attendance camera scanning** needs `localhost` or `https://`. On the laptop at `http://localhost/...` it works.
> Over a plain `http://192.168...` address the camera is blocked by the browser, so use **manual check-in** there.
> Online with InfinityFree's free SSL (`https://`) it works on any device.

---

## 2. Deploy to InfinityFree (FileZilla)

### Step 1: Create the hosting account and database
1. Create an account at InfinityFree and add a website (e.g. `myconference.infinityfreeapp.com`).
2. Control Panel → **MySQL Databases** → create a database (e.g. `studentregister`).
   Note the **MySQL Hostname** (e.g. `sql123.infinityfree.com`), **database name** (e.g. `if0_12345678_studentregister`),
   **username** (e.g. `if0_12345678`) and **password** (your vPanel / hosting account password).
3. Click **phpMyAdmin** next to the database → **Import** → choose `database/schema.sql` → **Go**.
   (Only `schema.sql`. Do not import `sample_data.sql`.)

> InfinityFree does not allow outside connections to its databases, so MySQL Workbench cannot connect to the live database.
> Use phpMyAdmin from the control panel instead.

### Step 2: Edit `config.php` (on your laptop, before uploading)
```php
define('DB_HOST', 'sql123.infinityfree.com');   // your MySQL Hostname
define('DB_NAME', 'if0_12345678_studentregister');
define('DB_USER', 'if0_12345678');
define('DB_PASS', 'your-hosting-password');
define('APP_DEBUG', false);                      // hide error details online
```

### Step 3: Upload with FileZilla
1. Control Panel → **FTP Details**: host (e.g. `ftpupload.net`), username, password, port `21`.
2. FileZilla → File → Site Manager → New site → enter the FTP details → Connect.
3. On the right side open the **`htdocs`** folder and delete the default `index2.html` file if there is one.
4. Upload **everything inside** the project folder into `htdocs`:
   `admin/`, `assets/`, `includes/`, `database/`, `index.php`, `register.php`, `confirmation.php`, `lookup.php`, `config.php`, `.htaccess`.
   (You don't need to upload `.git`.)
5. Make sure `.htaccess` was uploaded. In FileZilla enable *Server → Force showing hidden files* to check.

### Step 4: Turn on HTTPS
Control Panel → **SSL Certificates** → request the free certificate for your domain and install it.
After it's active, open your site with `https://`.

### Step 5: Go live
1. Open `https://your-domain/admin/` and log in with `admin` / `Admin@123`, then change the password.
2. **System Settings:** leave *Public Base URL* empty
   (or set it to `https://your-domain`).
3. Add your schools and courses (*System Settings → Schools, Courses & Lists*).
4. Create a conference. Its QR code is generated automatically. Print it from **QR Code Management → Print**.

> **Important:** QR codes printed while testing on your laptop contain your laptop's address.
> After deploying, download or print the QR codes again from the live site.

---

## 3. How to use

| Who | Steps |
|---|---|
| **Admin** | Login → Conference Management → **New Conference** → QR Code Management → **Print / PNG** → watch Dashboard & Participant Records → Confirm registrations → Attendance Monitoring on the event day → Reports |
| **Student** | Scan QR → choose Student / Faculty / Guest → fill out the form → **Review** → **Confirm & Submit** → save the confirmation & attendance QR |
| **Event day** | Attendance Monitoring → choose the conference → **Start Camera** and scan each participant's attendance QR (or search by Student ID / reference no.) |

**Roles**
- **Super Admin:** everything, including Admin Accounts, System Settings, creating/archiving/deleting conferences.
- **Conference Admin:** only the conferences assigned to them (edit details, participants, QR codes, attendance, reports).

**Registration status:** new registrations are *Pending* unless the conference has *Automatically confirm* turned on.
Checking in a pending participant asks whether to confirm them first. Cancelled registrations cannot be checked in.

---

## 4. Backups
**System Settings → Backup → Download Backup (.sql)**. Do this before and after every conference.
To restore: Workbench → *Server → Data Import*, or phpMyAdmin → *Import*.

---

## Project structure
```
index.php            Student portal home
register.php         Registration form (opened from the QR code)
confirmation.php     Printable confirmation + attendance QR
lookup.php           Find my registration
config.php           Database & app settings  ← edit this
admin/               Admin dashboard pages
includes/            Shared PHP (database, auth, helpers, layouts)
assets/css, js       Styles and scripts
assets/vendor        Bundled libraries (Chart.js, QR, xlsx, jsPDF, html5-qrcode, icons)
database/schema.sql       Database tables + default admin
database/sample_data.sql  Optional test data (local only)
```

## Troubleshooting
| Problem | Fix |
|---|---|
| "Cannot connect to the database" | Check `config.php` values; make sure MySQL is running; make sure `schema.sql` was imported into that database. |
| Phone can't open the QR link (testing) | Use the laptop's IP instead of `localhost` (see Step 6), same Wi-Fi, allow Apache in the firewall. |
| Camera doesn't start | Needs `https://` or `localhost`; allow camera permission in the browser. Use manual check-in otherwise. |
| Locked out after wrong passwords | Wait 15 minutes, or a Super Admin can unlock it in Admin Accounts. |
| Forgot the only Super Admin password | In phpMyAdmin/Workbench run: `UPDATE admins SET password_hash='$2y$10$4.ewLpxhN0HetaRhGEBHCeQrERleml6rN5491ZpP.SGSlRLqQkKEa', must_change_password=1, failed_attempts=0, locked_until=NULL WHERE username='admin';` (resets it to `Admin@123`). |
| 500 error on InfinityFree | Set `APP_DEBUG` to `true` temporarily to see the message; check that all folders were uploaded. |
