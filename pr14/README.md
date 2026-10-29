# Practical 14 — StudentHub Admin Dashboard

## Features
- Admin-only dashboard using the session created by `WT/pr10/login.php`.
- Dynamic counts for users, students, events, and active users.
- Latest user accounts, role labels, and active/inactive status.
- Dynamic admin navigation links.
- `audit_logs` records dashboard visits, audit-view actions, and account status changes.
- CSRF token validation for POST actions and prepared SQL statements.
- Responsive Bootstrap layout.

## Location
Copy the `pr14` folder into your existing project at:
`C:\xampp\htdocs\xampp\myproject\WT\pr14\`

## Before running
1. Start Apache and MySQL in XAMPP.
2. Ensure Practical 10 is working and the `secure_login` database contains the `users` table.
3. Ensure Practical 12 created `studenthub.events`.
4. Import `database.sql` in phpMyAdmin (or let `dashboard.php` create `audit_logs` automatically).
5. The dashboard checks `studenthub.students` first and then `college1.students`, because student data may be stored in either database depending on earlier practical setup.

## Run
1. Open `http://localhost/xampp/myproject/WT/pr10/login.php`.
2. Sign in using your admin account. In the original Practical 10 setup, the test login is `admin` / `admin123`.
3. Open `http://localhost/xampp/myproject/WT/pr14/dashboard.php`.

Do not use the student account to test the admin dashboard. An unauthenticated user is redirected to the Practical 10 login page; a logged-in non-admin receives an access-denied response.

## Screenshots to capture
- Admin dashboard with statistic cards.
- Latest registrations/users table.
- Audit log after refreshing dashboard.
- Change a different user's active/inactive status, then show the corresponding audit log row.
- Try opening the dashboard as a student to demonstrate access control.

## Notes
- The login implementation in Practical 10 does not store a registration timestamp. If the `created_at` column is absent, the latest accounts are ordered by user ID and the Registered column displays `Not recorded`.
- This is a local lab project. Change default credentials and add production-grade controls before deployment.
