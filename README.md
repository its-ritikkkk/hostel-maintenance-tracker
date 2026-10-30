# Hostel Maintenance and Complaint Tracker

**Hostel Scope:** Aquamarine Hostel, Indian Institute of Technology (ISM) Dhanbad  
**Course:** Web Development & Software Engineering Academic Lab Project  
**Technology Stack:** PHP 8.2 (Procedural/PDO), MariaDB 10.4 / MySQL, Vanilla JavaScript (ES6+), HTML5, and CSS3 Flexbox  
*(Pure native stack — Zero external frameworks, libraries, or dependencies).*

---

## 1. Project Overview

The **Hostel Maintenance and Complaint Tracker** is a specialized, role-segregated facility management and helpdesk system engineered exclusively for **Aquamarine Hostel at IIT (ISM) Dhanbad**. 

It eliminates error-prone physical complaint registers and unorganized messaging by providing two dedicated portals:

1. **Student Portal (`student.php`):**
   - **Strict Institutional Identity:** Enforces official IIT (ISM) Dhanbad student emails on registration in the format: `24JE0329@iitism.ac.in` (`^[0-9]{2}[a-zA-Z]{2}[0-9]{4}@iitism\.ac\.in$`).
   - **Convenient Auto-Suggest:** Real-time JavaScript listener automatically populates the email field as the student enters their admission number.
   - **Cryptographic Security:** Passwords are hashed using BCrypt via `password_hash()` and verified via `password_verify()`.
   - **Absolute Data Privacy:** Logged-in students can view **strictly their own complaints** (`WHERE c.student_id = ?`). Non-authenticated visitors see only the login/signup card.
   - **Ticket Lodging:** Direct issue reporting across 4 categories: *Electrical*, *Plumbing*, *Network*, and *Housekeeping* with auto-linked student details.
   - **Inspection Modal:** Modal dialog to view complete complaint details without leaving the page.

2. **Warden Portal (`warden.php`):**
   - **Administrative Authentication:** Dedicated warden login credentials (`warden` / `warden123`) with automatic session isolation (clears student session).
   - **Real-Time Workload Metrics:** Overview cards charting **Total Complaints**, **Submitted (Pending)**, **In Progress**, and **Resolved**.
   - **Master Complaint Overview:** Full registry of all hostel resident complaints with student name, admission number, category badge, and formatted submission date.
   - **Asynchronous Status Updates:** In-line status dropdown changes immediately update MariaDB using asynchronous `fetch()` without full page reloads.

3. **Direct Root Router (`index.php`):**
   - Seamless root routing that evaluates active session state: automatically forwards authenticated wardens to `warden.php` and students/visitors to `student.php`.

---

## 2. Required Software & Prerequisites

- **XAMPP** (with Apache 2.4+ and MariaDB/MySQL 10.4+, PHP 7.4 or PHP 8.x).
- **Web Browser** (Google Chrome, Mozilla Firefox, Microsoft Edge, or Safari).
- **Python 3.x** *(Optional, required only for recompiling the 5-page PDF report)*.

---

## 3. Database Setup (phpMyAdmin)

1. Open the XAMPP Control Panel and start **Apache** and **MySQL**.
2. Open your browser and navigate to `http://localhost/phpmyadmin`.
3. In the left panel, click **New** or open the **Databases** tab.
4. Create a database named: `hostel_complaints` (Collation: `utf8mb4_general_ci`).
5. Select the `hostel_complaints` database from the sidebar.
6. Click on the **Import** tab.
7. Click **Choose File** and select:
   ```
   database/hostel_complaints.sql
   ```
8. Click **Import** at the bottom. Both `students` and `complaints` tables with demo data will be created.

---

## 4. Running the Application on Localhost

1. Ensure the project directory is located in your XAMPP web root:
   ```
   C:\xampp\php\htdocs\tracker\
   ```
   *(or `C:\xampp\htdocs\tracker\` depending on your local installation)*
2. In your browser, open:
   ```
   http://localhost/tracker/
   ```
3. You will be automatically routed to the **Student Portal** (or **Warden Portal** if logged in as warden).

---

## 5. System Credentials & Roles

| Role | Access URL | Credentials | Capabilities |
| :--- | :--- | :--- | :--- |
| **Student** | `student.php` | Registered Admission No &amp; Password<br>*(e.g., `24JE0329`)* | Register account, lodge tickets, track status of own tickets. |
| **Warden** | `warden.php` | ID: `warden`<br>Password: `warden123` | View all hostel complaints, view metrics, change status to In Progress / Resolved. |

---

## 6. Email Format & Validation Rules

On student signup, institute email format is strictly enforced:

* **Format:** `24JE0329@iitism.ac.in`
* **Regex Pattern:** `/^[0-9]{2}[a-zA-Z]{2}[0-9]{4}@iitism\.ac\.in$/i`
  * `^[0-9]{2}`: 2 digits for cohort year (e.g., `24`)
  * `[a-zA-Z]{2}`: 2 letters for branch/entry program (e.g., `JE`)
  * `[0-9]{4}`: 4 digits for student roll number (e.g., `0329`)
  * `@iitism\.ac\.in$`: Official institute domain
* **Enforcement:** Client-side via HTML5 `pattern` attribute + Server-side via PHP `preg_match()`.

---

## 7. Project Architecture & File Directory

```
tracker/
│
├── index.php                 # Direct session-aware router (routes to student.php or warden.php)
├── student.php               # Student Portal: Centered Auth (Login/Signup) & Isolated Complaint Dashboard
├── warden.php                # Warden Portal: Metric cards, master complaints table & inline status updates
│
├── config/
│   └── db.php                # Centralized PDO connection with prepared statements & auto-migration
│
├── api/
│   ├── submit_complaint.php  # Validates and stores new complaints linked to student session
│   ├── update_status.php     # Warden-only API endpoint to update ticket status via AJAX
│   └── get_statuses.php      # Lightweight JSON endpoint for complaint statuses
│
├── assets/
│   ├── style.css             # Vanilla CSS layout with flexbox centering & display: flow-root
│   └── script.js             # Client-side JavaScript (Fetch API status updates, modal popup controller)
│
├── database/
│   └── hostel_complaints.sql # MariaDB schema definition and sample testing data
│
├── generate_report_pdf.py    # Headless Chrome script compiling the 5-page PDF report
├── project_report_source.html# Formatted HTML source for the 5-page project documentation
└── Hostel_Maintenance_Tracker_Project_Report.pdf # Comprehensive 5-page publication-grade PDF report
```

---

## 8. Security Implementation

- **SQL Injection Prevention:** 100% parameterized prepared statements via PDO with `PDO::ATTR_EMULATE_PREPARES => false`. Zero dynamic string concatenation in SQL queries.
- **Password Security:** Cryptographic hashing using BCrypt algorithms (`password_hash` with `PASSWORD_DEFAULT`).
- **Cross-Site Scripting (XSS) Mitigation:** All dynamic database values pass through `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` before HTML rendering.
- **Session & Privilege Isolation:** Explicit role segregation: student logins unset warden sessions, and warden logins unset student sessions.
- **CSS Margin Collapse Fix:** Employs `display: flow-root` on container elements to prevent vertical margin collapsing between parent and child elements.

---

## 9. Lab Evaluation & Demonstration Guide

1. **Verify Root Routing & Centered Login:**
   - Open `http://localhost/tracker/`. Confirm it immediately redirects to `student.php`.
   - Observe that the Login/Sign Up card is centered horizontally and vertically in the viewport between header and footer.
2. **Student Signup & Email Validation Test:**
   - Switch to **New Student Sign Up**.
   - Type Admission Number: `24JE0329`. Notice the email input automatically suggests `24je0329@iitism.ac.in`.
   - Try registering with `student@gmail.com` &rarr; Notice it is rejected with a format error.
   - Enter `24JE0329@iitism.ac.in` and complete sign up &rarr; Logs in directly to student dashboard with zero success banner clutter.
3. **Submit a Complaint:**
   - Notice student name and admission number are pre-filled as read-only.
   - Select Category (e.g. *Electrical*), enter Title and Description, click **Submit Complaint**.
   - Notice the ticket appears in **My Maintenance Complaints**.
4. **Student Privacy Verification:**
   - Verify that the table displays strictly complaints logged by this student.
5. **Warden Management & In-line Status Update:**
   - Open `http://localhost/tracker/warden.php` in a second window or browser.
   - Login with ID `warden` and Password `warden123`.
   - View the metrics overview cards (Total, Submitted, In Progress, Resolved).
   - In the master table, locate the student's ticket and change the status dropdown to **In Progress** or **Resolved**.
   - Observe that the status badge updates instantly on the page without full page reload.
6. **Project Report PDF:**
   - Open `Hostel_Maintenance_Tracker_Project_Report.pdf` to review the 5-page technical report covering all system specifications and verification matrices.
