# IIT (ISM) Dhanbad - Hostel Maintenance and Complaint Tracker
**Project P4 — Multi-Hostel Warden Management Upgrade**

A full-stack, responsive web application built for the students and hostel administration of the **Indian Institute of Technology (Indian School of Mines), Dhanbad**. The system features **multi-hostel warden isolation** across campus hostels (**Aquamarine**, **Amber**, **Jasper**, **Rosaline**, **Diamond**, etc.), allowing students to report issues and dedicated wardens to manage maintenance tickets within their respective hostel jurisdictions.

---

## 1. Technology Stack (Strict Requirements)

- **Frontend Structure:** Semantic HTML5
- **Styling & Layout:** Vanilla CSS3 (Custom design system, flexbox, CSS grid, responsive layout — No Bootstrap, No Tailwind)
- **Client-Side Logic:** Vanilla JavaScript (ES6+, Fetch API, DOM manipulation, 5s polling — No jQuery, No React, No external libraries)
- **Backend Logic:** Plain PHP 8.x (Session authentication, role-based & hostel-based access control, JSON REST APIs)
- **Database:** MariaDB / MySQL with relational tables (`hostels`, `users`, `complaints`, `complaint_updates`), foreign keys, and PDO prepared statements
- **Local Server Environment:** XAMPP (Apache + MariaDB)

---

## 2. Multi-Hostel Architecture & Directory Structure

```
tracker/
│
├── admin/                        # Multi-Hostel Warden Portal
│   ├── dashboard.php             # Common warden dashboard dynamically scoped to assigned hostel
│   ├── manage_complaints.php     # Registry of complaints restricted to warden's hostel
│   └── update_complaint.php      # Ticket view with timeline, remarks & strict hostel authorization
│
├── api/                          # Headless Asynchronous JSON Endpoints (Fetch API)
│   ├── get_complaint.php         # Returns single complaint details (enforces student & warden scope)
│   ├── get_complaints.php        # Returns filtered complaint lists (strictly scoped to warden hostel)
│   ├── get_status_updates.php    # Polling endpoint for 5-second live status refresh
│   ├── submit_complaint.php      # Asynchronously creates complaint with status 'Submitted' & hostel_id
│   └── update_status.php         # Updates status; verifies warden's assigned hostel (blocks tampering)
│
├── assets/                       # Static CSS & Vanilla JS
│   ├── css/
│   │   ├── dashboard.css         # Tables, KPI stat cards, filter bars, and timeline styling
│   │   ├── forms.css             # Input controls, auth cards, and multi-hostel demo boxes
│   │   └── style.css             # Core colors, typography, badges, and toast notifications
│   └── js/
│       ├── admin.js              # Inline AJAX status updater, filters, and polling for wardens
│       ├── complaints.js         # Async submission, filtering, and 5s polling for students
│       └── main.js               # Toast system, HTML entity escaping, and Fetch API wrapper
│
├── auth/                         # Authentication & Session Handlers
│   ├── login.php                 # Dual login for students & wardens (loads user_hostel_id)
│   ├── logout.php                # Session destruction and secure logout
│   └── register.php              # Student registration with hostel mapping
│
├── config/                       # System Configuration
│   └── db.php                    # Central PDO database connection with prepared statement security
│
├── database/                     # SQL Definitions and Migrations
│   ├── migration_multi_hostel.sql# Safe, non-destructive migration script for multi-hostel support
│   ├── sample_data.sql           # Complete seed data with 3 dedicated wardens and students
│   └── schema.sql                # Complete relational schema (hostels, users, complaints, updates)
│
├── includes/                     # Reusable Components & Authorization Guards
│   ├── auth.php                  # Multi-hostel guards (isWardenAuthorizedForHostel, requireAdmin), CSRF
│   ├── footer.php                # Institutional footer, toast container, and script loader
│   ├── header.php                # HTML5 head, CSS loader, BASE_URL setup, flash messages
│   └── navbar.php                # Multi-hostel aware responsive navigation bar
│
├── index.php                     # Landing page with live maintenance statistics & demo accounts
└── README.md                     # Comprehensive project documentation & viva guide
```

---

## 3. Database Schema & Migration

### Relational Table Design
1. **`hostels` Table:** Master registry of IIT (ISM) Dhanbad hostels (`id`, `name`, `code`, `description`).
2. **`users` Table:** Stores students and wardens. Includes `hostel_id` as a foreign key referencing `hostels(id)`.
   - For a **warden**, `hostel_id` defines the **exact hostel jurisdiction** they are authorized to manage.
   - For a **student**, `hostel_id` represents their resident hostel.
3. **`complaints` Table:** Associates every complaint with a `hostel_id` (foreign key) and `hostel` name string.
4. **`complaint_updates` Table:** Audit log recording status changes, remarks, timestamps, and the warden (`updated_by`) who executed the change.

### Running the Migration on Existing Database
If you already have the single-admin database running, execute `database/migration_multi_hostel.sql`. It is non-destructive and preserves all existing complaints:
```bash
mysql -u root -p < "C:\xampp\php\htdocs\tracker\database\migration_multi_hostel.sql"
```

For a completely fresh installation, import `database/schema.sql` followed by `database/sample_data.sql`.

---

## 4. Dedicated Warden Accounts & Student Demo Credentials

For laboratory practical evaluation, the following pre-configured accounts are active:

| Role / Jurisdiction | Email Address | Password | Assigned Hostel | Office / Room |
| :--- | :--- | :--- | :--- | :--- |
| **Aquamarine Warden** | `warden.aquamarine@iitism.ac.in` | `Warden@123` | Aquamarine Hostel | Warden Office, Aquamarine |
| **Amber Warden** | `warden.amber@iitism.ac.in` | `Warden@123` | Amber Hostel | Warden Office, Amber |
| **Jasper Warden** | `warden.jasper@iitism.ac.in` | `Warden@123` | Jasper Hostel | Warden Office, Jasper |
| **Student (Aquamarine)** | `aarav.21je0123@iitism.ac.in` | `Student@123` | Aquamarine Hostel | Room A-304 |
| **Student (Amber)** | `rohit.20je0789@iitism.ac.in` | `Student@123` | Amber Hostel | Room C-118 |
| **Student (Jasper)** | `sneha.23je0234@iitism.ac.in` | `Student@123` | Jasper Hostel | Room D-405 |

> **Viva Evaluation Tip:** The login page at `auth/login.php` features one-click demo chips to automatically fill credentials for all 3 wardens and students.

---

## 5. How Multi-Hostel Warden Authorization Works

### A. Server-Side Enforcement (Not Frontend Filtering)
1. **Session-Level Context:** When a warden signs in, their trusted `hostel_id` is fetched from MariaDB and saved in `$_SESSION['user_hostel_id']`.
2. **Strict Complaint Read Scoping:**
   - In `api/get_complaints.php`, if `$_SESSION['role'] === 'admin'`, the SQL query is strictly constrained:
     ```php
     if ($wardenHostelId !== null) {
         $sql .= ' AND c.hostel_id = :warden_hostel_id';
         $params['warden_hostel_id'] = $wardenHostelId;
     }
     ```
   - Even if an attacker modifies the URL query parameters (e.g. `?hostel=Amber+Hostel`), the backend ignores it and applies only the session's assigned `hostel_id`.
3. **ID Tampering Defense:**
   - In `api/update_status.php`, when a warden attempts to update complaint `#X`:
     ```php
     if ($wardenHostelId !== null && (int)$complaint['hostel_id'] !== $wardenHostelId) {
         http_response_code(403);
         echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to modify complaints for another hostel.']);
         exit;
     }
     ```
   - Attempting to update a ticket belonging to another hostel immediately returns **`HTTP 403 Forbidden`**.

### B. Common Warden Dashboard
Rather than creating redundant separate files, the single common dashboard in `admin/dashboard.php` and registry in `admin/manage_complaints.php` dynamically adapt to whichever warden is logged in, displaying only their hostel's tickets, stats, and KPI cards.

---

## 6. Asynchronous Real-Time Functionality

1. **Complaint Lodging:** Students submit tickets via Fetch API POST to `api/submit_complaint.php`. The ticket is saved with the associated `hostel_id` and initial status `Submitted`.
2. **Warden Status Update:** Wardens change ticket status directly via the inline `<select class="status-select-inline">` dropdown. Fetch API POST updates MariaDB and records the change in `complaint_updates` without reloading.
3. **5-Second Short Polling:** Every 5 seconds, client scripts (`complaints.js` and `admin.js`) query `api/get_status_updates.php`. The badge color and text update live with a smooth highlight animation.
4. **Duplicate Timer Prevention:** All polling timers are stored on `window.hostelTrackerTimer` / `window.adminTrackerTimer` and destroyed on `beforeunload`.

---

## 7. Verification & Manual Test Scenarios

| Test Case | Procedure | Expected Result | Result |
| :--- | :--- | :--- | :---: |
| **TC-01: Aquamarine Submission** | Student Aarav submits ticket for Aquamarine Hostel. | Saved in MariaDB with `hostel_id = 1` and status `Submitted`. | **PASS** |
| **TC-02: Aquamarine Warden Access** | Log in as Aquamarine Warden (`warden.aquamarine@iitism.ac.in`). | Dashboard shows Aquamarine complaints; ticket can be moved to `In Progress`. | **PASS** |
| **TC-03: Amber Warden Isolation** | Log in as Amber Warden (`warden.amber@iitism.ac.in`). | Amber warden sees only Amber complaints; Aquamarine ticket is hidden. | **PASS** |
| **TC-04: ID Tampering Defense** | Amber warden sends Fetch POST attempting to update Aquamarine ticket. | Request blocked with **HTTP 403 Forbidden**; ticket unchanged. | **PASS** |
| **TC-05: Jasper Warden Isolation** | Log in as Jasper Warden (`warden.jasper@iitism.ac.in`). | Jasper warden strictly sees Jasper Hostel complaints. | **PASS** |
| **TC-06: Student Live Polling** | Keep Student tab open while Warden updates status in another tab. | Student badge updates to `In Progress` within 5 seconds without refresh. | **PASS** |
| **TC-07: Unassigned Account Guard** | Attempt login with an admin account having `hostel_id = NULL`. | Helpful error page prompts user that a valid hostel assignment is required. | **PASS** |
| **TC-08: Unauthenticated Rejection** | Access API endpoints directly without session cookie. | Blocked with **HTTP 401 Unauthorized**. | **PASS** |

---

## 8. Viva Voce Preparation Guide (Multi-Hostel)

### Q1: How does your database schema support multiple hostels?
> **Answer:** We created a relational `hostels` master table (`id`, `name`, `code`). Both the `users` and `complaints` tables feature a `hostel_id` foreign key referencing `hostels(id)`. This links students and complaints to their resident hostel, and wardens to their assigned administrative jurisdiction without data duplication.

### Q2: Why is hostel authorization enforced on the server rather than just in the UI?
> **Answer:** Frontend filtering in JavaScript can easily be bypassed using browser developer tools, cURL, or Postman. By reading the warden's assigned `hostel_id` directly from the authenticated `$_SESSION` on the server and binding it to SQL prepared statements, the server guarantees that a warden cannot read or update tickets belonging to other hostels, even if they manipulate complaint IDs in the request.

### Q3: How did you migrate existing complaint data without losing records?
> **Answer:** In `database/migration_multi_hostel.sql`, we used an `UPDATE ... JOIN` query:  
> `UPDATE complaints c JOIN hostels h ON TRIM(c.hostel) = TRIM(h.name) SET c.hostel_id = h.id`.  
> This safely populated the foreign key `hostel_id` based on existing text values without dropping tables or losing any existing records.

### Q4: How does the warden dashboard adapt to different wardens without code duplication?
> **Answer:** We use a single, unified warden dashboard (`admin/dashboard.php`). When any warden logs in, PHP reads `$_SESSION['user_hostel_id']` and dynamically scopes the SQL queries (`WHERE hostel_id = :hostel_id`). The page title, hostel badge, statistics counters, and complaint registry automatically bind to the logged-in warden's hostel.
