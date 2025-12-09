# ✨ SanRoom: Schedule Management System

### _System for Automated Notification and Reorganization Of Operational Meetings_

---

<div align="center">

![PHP](https://img.shields.io/badge/PHP-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?logo=javascript&logoColor=black)
![HTML](https://img.shields.io/badge/HTML5-E34F26?logo=html5&logoColor=white)
![CSS](https://img.shields.io/badge/CSS3-1572B6?logo=css3&logoColor=white)

</div>

---

## 📌 Table of Contents

- [👥 Project Members](#-project-members)
- [📁 Project Overview & Tech Stack](#-project-overview--tech-stack)
- [📢 Real-Time Upgrade Requirement](#-real-time-upgrade-requirement)
- [🧑‍💻 Recent Developer Updates (Dec 2025)](#-recent-developer-updates-dec-2025)
- [🔐 Architecture, Schema & Security](#-architecture-schema--security)
- [🛠 Installation (XAMPP Guide)](#-installation-xampp-guide)
- [📄 License / Footer](#-license--footer)

---

## 👥 Project Members

- **Basco, Asher M.**
- **Boa, John Patrick M.**
- **Llada, John Rico Vincent R.**
- **Lirio, Justin Christopher B.**
- **Mendoza, Adrian Wesley M.**
- **Cerillo, Johnlery C.**

---

## 📁 Project Overview & Tech Stack

SanRoom is a lightweight, role-based schedule and room management web application designed for speed, accessibility, and low overhead. It uses a simple yet robust architecture suited for school, facility, and organizational resource planning.

### **Tech Stack**

- **Backend:** PHP (MySQLi)
- **Frontend:** Vanilla JavaScript, HTML, CSS
- **Database:** MySQL

### **Core Features**

- ✅ Schedule & Room lifecycle management (Create, Edit, Archive)
- ✅ Unified enrollment via intelligent access codes
- ✅ Cross-tab data sync using `localStorage` and polling
- ✅ Secure, session-based authentication

> 💡 _SanRoom is optimized for XAMPP/Apache and served from the `public/` directory._

---

## 📢 Real-Time Upgrade Requirement

The system currently uses polling and browser storage events for near-real-time updates.  
To achieve **true real-time notifications**, a shift to **WebSockets is required**.

### 📡 WebSockets Implementation Strategy

- **Decouple:** Deploy an independent WebSocket server  
  (Node.js + Socket.IO or PHP Ratchet)
- **Publish Hook:** Backend endpoints (e.g., `enroll_student.php`) push events after DB writes
- **Subscribe:** Frontend clients listen for room/schedule events for instant UI updates

---

## 🧑‍💻 Recent Developer Updates (Dec 2025)

### 🔑 Enrollment & Room Logic Refinement

- Unified endpoint (`enroll_student.php`) for both schedule-specific and room-wide enrollments
- Accurate room capacity via aggregated data from `schedule_participants`
- `save_schedule.php` auto-creates rooms when needed

---

### 🧑‍🏫 Teacher Activation & Robustness

- Single-use activation codes for first-time login
- QR code parsing improvements in `login.js`
- Moderator repair script: `repair_teacher_activation_codes.php`
- Endpoints accept both form-data and JSON

---

### 🧰 Moderator Tools & Audit Logging

- Moderator-only backend tools under `public/Moderator/backends/`
- Structured login debug logs (`debug_login.log`)
- `account_logs` table added for administrative action auditing

---

## 🔐 Architecture, Schema & Security

### 🗄 Database Schema Notes

The many-to-many relationship between students and schedules is managed through  
`schedule_participants`.

⚠ **Foreign Key Reminder**  
MySQL **requires matching INT types** between primary and foreign keys (signed/unsigned).  
Mismatches cause _Errno 150_ errors.

---

### 🛡 Security & Hardening Checklist

#### ✔ Implemented

- Secure password hashing (`password_hash`)
- Prepared statements (MySQLi)
- Mandatory password reset on first login
- Server-side trimming of sensitive inputs

#### ❗ Critical Missing Steps

- Enforce **HTTPS** in production
- Add **CSRF protection**
- Implement **Rate Limiting**
- Strengthen **Role-Based Access Control** for Super Admin pages

---

## 🛠 Installation (XAMPP Guide)

1. Move the project to `C:\xampp\htdocs\SanRoom`
2. Start Apache and MySQL in XAMPP
3. Create the `sanroom` database and import SQL migrations
4. Configure DB credentials in `database.php`
5. Open the system via:  
   **http://localhost/SanRoom/public/**

---

## 📄 License / Footer

Developed with ❤️ by the **SanRoom Development Team**  
**Basco | Boa | Llada | Lirio | Mendoza | Cerillo**  
© 2025

---
