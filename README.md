# Student Complaint Management System

A modern, secure, and fully responsive web-based portal designed to streamline student complaint lodging, tracking, and resolution. Built with a custom premium aesthetic, real-time communication mechanisms, and robust administrative oversight tools.

## 🚀 Live Demo
Access the live platform on any device:  
👉 **[Student Complaint Management System Portal](https://student-complaint-management-system-d2tg.onrender.com)**

---

## 🛠️ Technology Stack
* **Backend:** PHP 8.x (Session handling, MVC pattern, Prepared SQL statements)
* **Frontend:** Javascript (ES6), HTML5, Vanilla CSS, Tailwind CSS (via CDN)
* **Database:** MySQL (Clever Cloud Host)
* **Deployment:** Dockerized environment hosted on Render

---

## ✨ Key Features

### 👤 Student Features
* **Interactive Dashboard:** Full-width responsive layout with a slide-out hamburger navigation drawer.
* **File a Complaint:** Lodge detailed complaints with priority levels, categories, and support for document/image attachments.
* **Real-time Status Tracking:** Dynamic timeline tracker showing progress updates (Pending ➜ Under Review ➜ In Progress ➜ Resolved/Closed).
* **Threaded Discussion:** Discuss complaints directly with assigned departments, featuring double-submit safety preventions and interactive editing/deleting of replies.

### 💼 Administrative Features
* **Admin Control Center:** Overview dashboard showcasing metrics cards for total, pending, resolved, closed, and rejected tickets.
* **Smart Filter Queues:** Search and filter complaints by student matric number, department, category, priority, and status.
* **Administrative Audit Logs:** Detailed print-ready analytical reports with signature verification sections.
* **Staff Directory & Management:** Super admins can provision, suspend, or update accounts for department personnel.
* **In-App Notifications:** Real-time notifications for incoming complaints and new discussion messages.

### 🔒 Security & UX Optimization
* **Tab-Switch Auto-Logout:** Automatic session expiration protection when tabs are closed or minimized (preserves active text input).
* **Double-Submit Prevention:** Intercepts clicks on submit buttons, disabling them and rendering a spinner during database operations to prevent duplicated entries.
* **High-Fidelity Dialog Modals:** Modern, dark-themed overlay modals confirming logout requests (Yes/No prompt).
* **Global Theme Toggle:** Switch seamlessly between light and dark modes.

---

## 💻 Local Installation (Using XAMPP)

1. **Clone the repository:**
   ```bash
   git clone https://github.com/MarvelousAnthony/student-complaint-management-system.git
   ```
2. **Move files to XAMPP htdocs:**
   Copy the cloned folder into your local XAMPP directory:
   `C:\xampp\htdocs\complaint_system\`

3. **Database Setup:**
   * Open XAMPP Control Panel and start **Apache** and **MySQL**.
   * Go to `http://localhost/phpmyadmin/` in your browser.
   * Create a new database named `bp2crspatt7riatr2nr6` (or any custom name).
   * Import the `schema.sql` (or `database.sql`) file located in the root of the project folder.

4. **Update Connection Parameters:**
   Edit the database credentials inside [db_connect.php](db_connect.php) to point to your local database configuration:
   ```php
   $servername = "localhost";
   $username = "root";
   $password = "";
   $dbname = "your_local_database_name";
   ```

5. **Launch Portal:**
   Access the local webserver through your browser:
   `http://localhost/complaint_system/login.php`

---

## 📄 License
This project is proprietary and intended for student governance and educational record audits at Adeleke University. All rights reserved.
