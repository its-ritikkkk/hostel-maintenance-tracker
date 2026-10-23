# Hostel Maintenance and Complaint Tracker

**Hostel Scope:** Aquamarine Hostel, IIT (ISM) Dhanbad  
**Course:** College Web Development Lab (P4 Group Project)  
**Technology Stack:** Plain HTML, CSS, JavaScript (Vanilla), PHP (Procedural), and MariaDB/MySQL.  
*(No external frameworks or libraries — 100% beginner-friendly and clean code).*

---

## 1. Project Overview

The **Hostel Maintenance and Complaint Tracker** is a dedicated web-based helpdesk created specifically for **Aquamarine Hostel at IIT (ISM) Dhanbad**. 

It provides two distinct roles:
1. **Student:** Residents can submit new maintenance complaints across 4 categories (*Electrical*, *Plumbing*, *Network*, *Housekeeping*), filter their complaints, and monitor live status updates (`Submitted`, `In Progress`, `Resolved`) without reloading their browser page.
2. **Warden (Admin):** The hostel warden manages all submitted complaints, reviews full details, and updates complaint progress using an intuitive status dropdown that immediately saves to MariaDB.

---

## 2. Required Software

- **XAMPP** (or any LAMP/WAMP stack with PHP 7.4+ or PHP 8.x and MariaDB/MySQL).
- **Web Browser** (Chrome, Firefox, Edge, or Safari).

---

## 3. Database Setup (Using phpMyAdmin)

1. Open your browser and go to `http://localhost/phpmyadmin` (ensure Apache and MySQL/MariaDB are started in XAMPP Control Panel).
2. Click on the **Databases** tab.
3. Enter database name: `hostel_complaints` and click **Create**.
4. Select the newly created `hostel_complaints` database from the left sidebar.
5. Click on the **Import** tab at the top.
6. Click **Choose File** and select:
   ```
   database/hostel_complaints.sql
   ```
7. Click the **Import** button at the bottom of the page.
8. The tables (`students` and `complaints`) along with sample demo records will be imported successfully.

---

## 4. Running the Project on Localhost

1. Ensure the project folder is placed inside your XAMPP `htdocs` directory:
   ```
   C:\xampp\htdocs\tracker\
   ```
   *(or `C:\xampp\php\htdocs\tracker\` depending on your local XAMPP install path)*
2. Verify that **Apache** and **MySQL** are running in the XAMPP Control Panel.
3. Open your browser and navigate to:
   ```
   http://localhost/tracker/
   ```
   *(or if running via PHP built-in server: `http://localhost:8000/`)*
4. You will see the main landing page with live statistics and links to the **Student Portal** and **Warden Dashboard**.

---

## 5. How Asynchronous Status Refresh Works

One of the key requirements of this project is demonstrating **asynchronous communication** between JavaScript in the browser, PHP on the server, and MariaDB in the database:

1. **Periodic Polling (`setInterval`)**:
   In `assets/script.js`, a JavaScript interval timer runs every **5 seconds**:
   ```javascript
   setInterval(refreshComplaintStatuses, 5000);
   ```

2. **Fetching Updated Data (`fetch()`)**:
   Every 5 seconds, `fetch('api/get_statuses.php')` sends an HTTP GET request in the background without refreshing the browser.

3. **Database Query (PHP + MariaDB)**:
   The PHP script `api/get_statuses.php` queries the MariaDB `complaints` table:
   ```sql
   SELECT id, status FROM complaints;
   ```
   It returns the result as lightweight JSON:
   ```json
   {
     "success": true,
     "timestamp": "19:45:10",
     "statuses": [
       {"id": 1, "status": "In Progress"},
       {"id": 2, "status": "Resolved"}
     ]
   }
   ```

4. **Live DOM Update & Visual Flash**:
   When JavaScript receives the JSON array, it checks each complaint row on the screen:
   - If a status has changed (e.g. from `Submitted` to `In Progress`), it updates the badge text and CSS class (`status-submitted`, `status-in-progress`, `status-resolved`).
   - It triggers a CSS highlight animation (`status-updated`) so the student immediately notices the live change.
   - The "Last Synced" clock in the top corner updates to show active connectivity.

---

## 6. Project Folder Structure & File Purpose

```
tracker/
│
├── index.php                 # Main landing page showing summary metrics and recent complaints
├── student.php               # Student complaint interface (submission form + complaint list)
├── warden.php                # Warden administration dashboard to review and change statuses
│
├── config/
│   └── db.php                # Database credentials and secure PDO connection with prepared statements
│
├── api/
│   ├── submit_complaint.php  # Handles new complaint submissions and student identification
│   ├── get_complaints.php    # Returns filtered list of complaints with student details
│   ├── update_status.php     # Updates complaint status in MariaDB (Submitted / In Progress / Resolved)
│   └── get_statuses.php      # Lightweight JSON endpoint queried every 5 seconds for async refresh
│
├── assets/
│   ├── style.css             # Clean vanilla CSS design (custom badges, responsive tables, cards)
│   └── script.js             # Client-side JavaScript for fetch(), periodic polling, and filters
│
└── database/
    └── hostel_complaints.sql # MariaDB schema creation and realistic sample testing data
```

---

## 7. Lab Demonstration Quick Guide

When demonstrating this project to the lab examiner:

1. **Show Home Page (`index.php`)**:
   - Point out the fixed hostel scope: **Aquamarine Hostel, IIT (ISM) Dhanbad**.
   - Show the summary metric boxes (Total Complaints, Submitted, In Progress, Resolved).
2. **Submit a Complaint as Student (`student.php`)**:
   - Pick or enter student admission number (e.g. `23JE0501`).
   - Fill out Title, Category (`Electrical`, `Plumbing`, `Network`, `Housekeeping`), and Description.
   - Click **Submit Maintenance Request** &rarr; Observe the new complaint appear with default status **Submitted**.
3. **Filter Complaints**:
   - Use the Category and Status dropdowns to filter complaints instantly.
4. **Demonstrate Asynchronous Status Update**:
   - Open `student.php` in one tab and `warden.php` in a second tab.
   - On `warden.php`, change the complaint status from `Submitted` to `In Progress`.
   - Switch to the student tab and wait up to 5 seconds &rarr; The badge automatically flashes and changes to `In Progress` **without any manual page refresh**!
