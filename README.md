# Hostel Maintenance and Complaint Tracker

**Hostel Scope:** Aquamarine Hostel, Indian Institute of Technology (ISM) Dhanbad  

**Technology Stack:** PHP 8.2 (Procedural / PDO), MariaDB 10.4 / MySQL, Vanilla JavaScript (ES6+), HTML5, CSS3 Flexbox  


---

## 1. Description of Code Files

This project is organized into presentation views, RESTful API endpoints, shared configurations, client-side assets, and database schemas:

```
tracker/
├── index.php                 # Root application router and session redirector
├── student.php               # Student Portal (Authentication, Ticket Lodging & My Complaints)
├── warden.php                # Warden Portal (Administrative Dashboard, Metrics & Status Updates)
│
├── config/
│   └── db.php                # Database connection factory via PDO with prepared statements
│
├── api/
│   ├── submit_complaint.php  # Handles new complaint submissions (Session-authenticated)
│   ├── update_status.php     # Updates complaint resolution status in MariaDB (Warden-only)
│   └── get_statuses.php      # Lightweight JSON endpoint returning complaint statuses
│
├── assets/
│   ├── style.css             # Application stylesheet (Flexbox centering, badges, modal, clean tables)
│   └── script.js             # Client-side JavaScript (Fetch API status updates, modal popup logic)
│
└── database/
    └── hostel_complaints.sql # MariaDB schema definitions and testing seed records
```

### Detailed File Responsibilities

| File Path | Component | Functional Description |
| :--- | :--- | :--- |
| **`index.php`** | **Root Router** | Acts as the primary application entry point at `http://localhost/tracker/`. Evaluates the user's active session state: automatically forwards authenticated wardens to `warden.php` and students or unauthenticated visitors directly to `student.php`, eliminating redundant public landing pages. |
| **`student.php`** | **Student Portal View** | **Dual-state user interface:**<br>1. *Unauthenticated State:* Displays a horizontally and vertically centered authentication card for **Student Login** and **New Student Sign Up** with strict IIT (ISM) email validation (`24JE0329@iitism.ac.in`).<br>2. *Authenticated State:* Displays the student session badge, complaint lodging form with auto-linked student information, and a personalized **My Maintenance Complaints** table showing strictly the logged-in student's complaints. |
| **`warden.php`** | **Warden Portal View** | **Administrative command console:**<br>1. *Login State:* Centered administrative login card requiring fixed credentials (`warden` / `warden123`).<br>2. *Dashboard State:* Real-time metric cards (*Total*, *Submitted*, *In Progress*, *Resolved*) and the master complaints registry showing all hostel issues with in-line status update dropdowns and ticket inspection buttons. |
| **`config/db.php`** | **Database Configuration** | Implements `getDbConnection()` returning an active PDO instance connected to MariaDB (`127.0.0.1:3306`, database `hostel_complaints`). Sets `PDO::ATTR_EMULATE_PREPARES => false` for genuine SQL Injection immunity and `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` for structured error handling. Also executes dynamic schema verification for table structure. |
| **`api/submit_complaint.php`** | **API Controller** | Receives and validates complaint submissions. Enforces session authentication (`$_SESSION['student_id']`) so only logged-in students can lodge tickets. Binds tickets strictly to the verified student ID, validates category against allowed whitelist (`Electrical`, `Plumbing`, `Network`, `Housekeeping`), and writes to the `complaints` table with initial status `Submitted`. |
| **`api/update_status.php`** | **API Controller** | Role-protected administrative API endpoint. Verifies active warden session (`$_SESSION['warden_logged_in']`), validates target status (`Submitted`, `In Progress`, `Resolved`), and executes an atomic `UPDATE` query on MariaDB. Returns JSON response for immediate client-side badge updating. |
| **`api/get_statuses.php`** | **API Controller** | Returns complaint IDs and current statuses as lightweight JSON. Scoped automatically to the active student session (or all tickets for wardens), enabling client-side status synchronization. |
| **`assets/style.css`** | **Styles & Layout** | Complete native CSS design without external frameworks. Features:<br>• Viewport centering for authentication forms (`.main-wrapper.auth-main`)<br>• CSS margin-collapse resolution via `display: flow-root`<br>• Sticky footer pinning via flexbox layout (`min-height: 100vh; margin-top: auto`)<br>• Color-coded category tags and status badges (*Submitted*, *In Progress*, *Resolved*)<br>• Clean, responsive complaint tables and modal overlay styles. |
| **`assets/script.js`** | **Client-side Controller** | Pure ES6+ JavaScript handling client interactivity:<br>• In-line status change listener sending asynchronous `fetch()` requests to `api/update_status.php` with instant badge updates without full page reload<br>• Modal dialog controller reading row `data-*` attributes to display full complaint descriptions in a focused popup overlay. |
| **`database/hostel_complaints.sql`** | **Database Definition** | Relational SQL schema for MariaDB/MySQL containing `CREATE TABLE` statements for `students` and `complaints` with foreign keys (`ON DELETE CASCADE`), indexes, and realistic demo records for Aquamarine Hostel. |

---

## 2. Steps to Deploy and Execute the Code

Follow these step-by-step instructions to set up, deploy, and run the project locally using XAMPP on Windows, Linux, or macOS.

### Step 1: Prerequisites & Software Setup
Ensure you have installed:
1. **XAMPP** (or any LAMP/WAMP/MAMP bundle) with:
   - **PHP 7.4+** or **PHP 8.x**
   - **Apache 2.4+**
   - **MariaDB 10.4+** or **MySQL 5.7+**
2. A modern web browser (Google Chrome, Mozilla Firefox, Microsoft Edge, or Safari).

---

### Step 2: Deploy Project Files to the Web Server
1. Clone or copy the `tracker` project folder into your XAMPP web root (`htdocs` directory):
   - **Windows Default:** `C:\xampp\htdocs\tracker\` *(or `C:\xampp\php\htdocs\tracker\`)*
   - **Linux Default:** `/opt/lampp/htdocs/tracker/`
   - **macOS Default:** `/Applications/XAMPP/xamppfiles/htdocs/tracker/`
2. Verify that the file structure matches the tree described in Section 1.

---

### Step 3: Start Web Server and Database Services
1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.
4. Ensure both service status indicators turn green.

---

### Step 4: Create and Import the Database
1. Open your web browser and navigate to **phpMyAdmin**:
   ```
   http://localhost/phpmyadmin/
   ```
2. Click on the **Databases** tab at the top.
3. Under **Create database**:
   - Database name: `hostel_complaints`
   - Collation: `utf8mb4_general_ci`
   - Click **Create**.
4. Select the newly created `hostel_complaints` database from the left sidebar.
5. Click on the **Import** tab in the top navigation bar.
6. Click **Choose File** and browse to the project's SQL file:
   ```
   tracker/database/hostel_complaints.sql
   ```
7. Click **Import** at the bottom of the page.
8. Verify that both `students` and `complaints` tables are created with sample records.

---

### Step 5: Verify Database Connection Configuration
Open `config/db.php` and verify the database connection credentials match your local setup:
```php
$db_host = '127.0.0.1';
$db_port = '3306';
$db_name = 'hostel_complaints';
$db_user = 'root';
$db_pass = ''; // Default XAMPP password is empty
```
*(If your local MySQL root account uses a password, specify it in `$db_pass`).*

---

### Step 6: Execute the Application
1. Open your web browser and navigate to:
   ```
   http://localhost/tracker/
   ```
2. `index.php` will automatically route you to the **Student Portal** (`student.php`).

---

## 3. User Roles, Credentials & Workflows

### Role 1: Student Workflow (`student.php`)
1. **Registration (New Student Sign Up):**
   - Click **New Student Sign Up**.
   - Enter **Full Name** (e.g. `Ritik Kumar`).
   - Enter **Admission Number** (e.g. `24JE0329`).
   - Notice the **Institute Email** field automatically suggests `24je0329@iitism.ac.in`.
   - *Note on Email Validation:* The email format strictly enforces the IIT (ISM) format: `^[0-9]{2}[a-zA-Z]{2}[0-9]{4}@iitism\.ac\.in$`.
   - Enter a password and click **Create Account & Login**.
2. **Lodging a Complaint:**
   - Once logged in, student details (Name, Admission Number, Email) are pre-filled as read-only.
   - Select a category (*Electrical*, *Plumbing*, *Network*, or *Housekeeping*).
   - Enter Room / Location & Issue title (e.g., `Room B-204 Ceiling Fan Regulator`).
   - Enter a detailed description and click **Submit Complaint**.
3. **Tracking Complaints (Data Privacy):**
   - The submitted ticket appears immediately in the **My Maintenance Complaints** table with default status `Submitted`.
   - Students see **strictly their own tickets** (`WHERE c.student_id = ?`). Other students' tickets are completely hidden.
   - Click **View** on any row to inspect complete details in the modal dialog.

---

### Role 2: Warden Workflow (`warden.php`)
1. **Warden Login:**
   - Click **Warden Portal** in the navigation header.
   - Fixed Credentials:
     - **Warden ID:** `warden`
     - **Password:** `warden123`
   - Click **Login as Warden**.
2. **Managing Complaints:**
   - Review top metric summary cards: **Total Complaints**, **Submitted**, **In Progress**, and **Resolved**.
   - Review all hostel complaints logged across Aquamarine Hostel.
   - **Update Status:** Change the dropdown on any complaint row from `Submitted` &rarr; `In Progress` &rarr; `Resolved`.
   - The status updates asynchronously in MariaDB via AJAX `fetch()` and reflects immediately on the badge.
3. **Logging Out:**
   - Click **Logout** in the navigation bar to destroy the session.

---

## 4. Security & Architecture Highlights

1. **SQL Injection Immunity:** All database interactions across all pages use PDO prepared statements with bound parameters (`$stmt->execute([$params])`). Emulated prepares are disabled.
2. **Cross-Site Scripting (XSS) Prevention:** All dynamic data rendered into HTML is sanitized using `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
3. **Session Privilege Segregation:** Logging in as a warden automatically terminates active student sessions, and logging in as a student clears warden sessions, preventing cross-role privilege confusion.
4. **Endpoint Protection:** `api/submit_complaint.php` strictly verifies `$_SESSION['student_id']` and rejects unauthorized POST submissions; `api/update_status.php` strictly verifies `$_SESSION['warden_logged_in']`.
5. **CSS Margin Collapse Fix:** Employs `display: flow-root` on parent containers to ensure child margins render predictably without vertical collapsing.

---

## 5. Troubleshooting Common Issues

* **Database Connection Error (`Could not connect to MariaDB`):**
  - Ensure MySQL service is running in XAMPP Control Panel.
  - Verify that database `hostel_complaints` has been imported in phpMyAdmin.
  - Check host and port in `config/db.php` (`127.0.0.1` on port `3306`).
* **Port Conflict on Apache (Port 80 / 443 in use):**
  - In XAMPP Control Panel &rarr; Click **Config** on Apache &rarr; `httpd.conf` &rarr; Change `Listen 80` to `Listen 8080`.
  - Access the application at: `http://localhost:8080/tracker/`.
* **Invalid Email Format Error on Signup:**
  - Institute email must strictly match the IIT (ISM) format: `24JE0329@iitism.ac.in` (2 digits + 2 letters + 4 digits + `@iitism.ac.in`).
