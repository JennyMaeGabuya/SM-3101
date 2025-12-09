<div id="sanroom-readme" style="max-width: 950px; margin: 0 auto; padding: 25px; border: 1px solid #ddd; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';">

<h1 align="center" style="border-bottom: 3px double #2ecc71; padding-bottom: 15px; color: #2980b9; font-size: 2.5em;">
    ✨ SanRoom: Schedule Management System ✨
</h1>
<p align="center" style="font-size: 1.2em; color: #555; margin-top: -10px;">
    System for Automated Notification and Reorganization Of Operational Meetings
</p>

<hr style="border-top: 1px dashed #ccc; margin: 20px 0;">

<section id="summary" style="margin-bottom: 35px;">
    <h2 style="color: #34495e; border-left: 6px solid #e74c3c; padding-left: 15px; font-size: 1.6em;">&#128193; Project Overview & Tech Stack</h2>
    <p style="line-height: 1.6; font-size: 1.05em;">
        SanRoom is a lightweight, role-based schedule and room management web application. It is engineered for low overhead and quick deployment, leveraging established, reliable core technologies.
    </p>
    <div style="display: flex; justify-content: space-around; background-color: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px;">
        <span style="font-weight: bold; color: #2c3e50;">⚙️ Backend: PHP (MySQLi)</span>
        <span style="font-weight: bold; color: #2c3e50;">🚀 Frontend: Vanilla JavaScript, HTML, CSS</span>
        <span style="font-weight: bold; color: #2c3e50;">🗄️ Database: MySQL</span>
    </div>
    <ul style="list-style-type: none; padding-left: 0; margin-top: 20px;">
        <li style="margin-bottom: 8px; color: #27ae60;">✅ **Schedule & Room Lifecycle Management** (Create, Edit, Archive).</li>
        <li style="margin-bottom: 8px; color: #27ae60;">✅ **Unified Enrollment Flow** using smart access codes (Schedule or Room).</li>
        <li style="margin-bottom: 8px; color: #27ae60;">✅ **Cross-Tab Data Sync** via `localStorage` and polling fallback for real-time views.</li>
        <li style="margin-bottom: 8px; color: #27ae60;">✅ **Secure Session-Based** Authentication & Registration.</li>
    </ul>
    <p style="font-size: 0.9em; margin-top: 20px; background-color: #ecf0f1; padding: 12px; border-radius: 6px; border-left: 4px solid #3498db;">
        💡 **Deployment Context:** The repository structure is optimized for local development under the **XAMPP/Apache** environment, serving files primarily from the <code>public/</code> directory.
    </p>
</section>

<hr style="border-top: 1px dashed #ccc; margin: 20px 0;">

<section id="realtime-needed" style="margin-bottom: 35px; border: 3px solid #e74c3c; padding: 20px; border-radius: 10px; background-color: #fdf6f6;">
    <h2 style="color: #e74c3c; font-size: 1.8em; text-align: center;">&#128226; Critical Next Step: Achieving True Real-Time</h2>
    <p style="font-size: 1.1em; line-height: 1.6; text-align: center;">
        Currently, the system achieves **near-real-time** sync through polling and local storage events. To eliminate latency, reduce server load, and deliver instant notifications, a shift to **WebSockets** is mandatory.
    </p>

    <h3 style="color: #c0392b; margin-top: 20px; border-bottom: 1px dashed #c0392b; padding-bottom: 5px;">WebSockets Implementation Strategy:</h3>
    <ul style="list-style-type: square; padding-left: 30px;">
        <li>**Decouple:** Implement an independent WebSocket server (e.g., using Node.js/Socket.IO or Ratchet for PHP) outside the standard Apache request lifecycle.</li>
        <li>**Publish Hook:** Modify key backend endpoints (like <code>enroll_student.php</code> or <code>save_schedule.php</code>) to send a payload to the WebSocket server upon successful DB write.</li>
        <li>**Subscribe:** Update frontend scripts (<code>roomManagement.js</code>) to connect to the WebSocket server and listen for room/schedule specific events, triggering instant UI updates rather than waiting for the next poll.</li>
    </ul>

</section>

<hr style="border-top: 1px dashed #ccc; margin: 20px 0;">

<section id="developer-updates" style="margin-bottom: 35px;">
    <h2 style="color: #34495e; border-left: 6px solid #f39c12; padding-left: 15px; font-size: 1.6em;">&#128187; Recent Developer Updates (Dec 2025 Sprint)</h2>

    <details open style="margin-top: 15px;">
        <summary style="font-weight: bold; cursor: pointer; color: #2980b9; font-size: 1.2em; padding: 5px 0;">&#128273; Enrollment & Room Logic Refinement</summary>
        <div style="padding: 15px; margin-top: 10px; border: 1px solid #dcdcdc; border-radius: 6px; background-color: #fff;">
            <p>The core logic for tracking participants and managing access codes has been centralized and hardened:</p>
            <ul style="padding-left: 20px; list-style-type: circle;">
                <li>**Unified Endpoint:** <code>enroll_student.php</code> now dynamically handles both schedule-specific (one-to-many) and room-wide (many-to-many) enrollments based on the provided access code type.</li>
                <li>**Room Aggregation:** Room capacity status (<code>current_participants</code>) is now accurately computed via aggregation across the new <code>schedule_participants</code> mapping table.</li>
                <li>**Data Reconciliation:** <code>save_schedule.php</code> ensures data integrity by creating new `rooms` entries if a valid name is provided but the room ID is missing.</li>
            </ul>
            <p style="margin-top: 15px;">&#128202; **Flowchart:** See the unified enrollment logic visualized below:</p>

        </div>
    </details>

    <details style="margin-top: 15px;">
        <summary style="font-weight: bold; cursor: pointer; color: #2980b9; font-size: 1.2em; padding: 5px 0;">&#128275; Teacher Activation & Robustness</summary>
        <div style="padding: 15px; margin-top: 10px; border: 1px solid #dcdcdc; border-radius: 6px; background-color: #fff;">
            <p>Teacher onboarding is now more secure and fault-tolerant:</p>
            <ul style="padding-left: 20px; list-style-type: circle;">
                <li>**One-Time Codes:** Teacher creation generates a unique, single-use activation code. This code is cleared immediately after the first successful password set.</li>
                <li>**QR Parsing:** Frontend JS (<code>login.js</code>) is enhanced to reliably parse new labeled QR strings (e.g., `Email:...|Code:...`) and auto-fill credentials for first-time login.</li>
                <li>**Admin Repair:** The dedicated <code>repair_teacher_activation_codes.php</code> script was added for moderators to safely restore or distribute lost activation codes.</li>
                <li>**Compatibility:** Endpoints now accept both standard form-data and JSON payloads (via <code>php://input</code>) to support cleaner modern AJAX clients.</li>
            </ul>
        </div>
    </details>

    <details style="margin-top: 15px;">
        <summary style="font-weight: bold; cursor: pointer; color: #2980b9; font-size: 1.2em; padding: 5px 0;">&#128188; Moderator Tools & Audit Logging</summary>
        <div style="padding: 15px; margin-top: 10px; border: 1px solid #dcdcdc; border-radius: 6px; background-color: #fff;">
            <p>Super Admin utilities and necessary auditing were implemented:</p>
            <ul style="padding-left: 20px; list-style-type: circle;">
                <li>**Admin Backends:** New moderator-only directories (<code>public/Moderator/backends/</code>) house sensitive tools (e.g., account creation, repair scripts).</li>
                <li>**Structured Logging:** Non-sensitive, structured debug logs (e.g., <code>debug_login.log</code>) were implemented to aid troubleshooting login failures and activation code issues.</li>
                <li>**Audit Trail:** The <code>account_logs</code> table is mandatory for tracking all administrative actions, ensuring non-repudiation for teacher account creation.</li>
            </ul>
        </div>
    </details>

</section>

<hr style="border-top: 1px dashed #ccc; margin: 20px 0;">

<section id="architecture-security" style="margin-bottom: 35px;">
    <h2 style="color: #34495e; border-left: 6px solid #8e44ad; padding-left: 15px; font-size: 1.6em;">&#128737; Architecture, Schema, and Security</h2>

    <h3 style="color: #2c3e50; margin-top: 20px;">&#128214; Database Schema Overview</h3>
    <p>
        The system relies on a clean relational structure, with <code>schedule_participants</code> handling the many-to-many relationship between students and schedules.
    </p>


    <h4 style="color: #2c3e50; margin-top: 20px;">Key Database Note (Foreign Keys)</h4>
    <p style="background-color: #fff3cd; padding: 10px; border-radius: 4px; border-left: 4px solid #ffc107;">
        ⚠️ **ATTENTION:** Ensure the integer signedness (<code>INT</code> vs. <code>INT UNSIGNED</code>) of Foreign Key columns (e.g., <code>schedule_participants.schedule_id</code>) **exactly matches** their respective Primary Keys (e.g., <code>schedules.id</code>) to avoid MySQL **Errno 150** errors.
    </p>

    <h3 style="color: #2c3e50; margin-top: 25px;">&#128272; Security & Hardening Checklist</h3>
    <div style="display: flex; gap: 20px; margin-top: 15px;">
        <div style="flex: 1; border: 1px solid #2ecc71; padding: 15px; border-radius: 8px; background-color: #f6fff6;">
            <h4 style="color: #2ecc71;">Implemented Safeguards</h4>
            <ul style="list-style-type: none; padding-left: 0; font-size: 0.95em;">
                <li style="color: #27ae60;">&#10003; Secure **Password Hashing** (`password_hash`).</li>
                <li style="color: #27ae60;">&#10003; **Prepared Statements** (MySQLi) for all DB writes.</li>
                <li style="color: #27ae60;">&#10003; Forced password change on first login.</li>
                <li style="color: #27ae60;">&#10003; **Server-side trimming** of all critical user inputs.</li>
            </ul>
        </div>
        <div style="flex: 1; border: 1px solid #e74c3c; padding: 15px; border-radius: 8px; background-color: #fff6f6;">
            <h4 style="color: #e74c3c;">Critical Missing Steps</h4>
            <ul style="list-style-type: none; padding-left: 0; font-size: 0.95em;">
                <li style="color: #c0392b;">&#9888; **Enforce HTTPS** in all production environments.</li>
                <li style="color: #c0392b;">&#9888; Implement **CSRF Tokens** for all state-changing forms.</li>
                <li style="color: #c0392b;">&#9888; Add **Rate Limiting** to login and enrollment endpoints.</li>
                <li style="color: #c0392b;">&#9888; Rigorous **Role-Based Access Control** on Super Admin pages.</li>
            </ul>
        </div>
    </div>

</section>

<hr style="border-top: 1px dashed #ccc; margin: 20px 0;">

<section id="installation" style="margin-bottom: 20px;">
    <h2 style="color: #34495e; border-left: 6px solid #3498db; padding-left: 15px; font-size: 1.6em;">&#128279; Installation & Local Run (XAMPP Guide)</h2>
    <ol style="padding-left: 30px; font-size: 1.0em;">
        <li>Place the project under your Apache webroot, e.g. <code>C:\xampp\htdocs\SanRoom</code>.</li>
        <li>Start **Apache** and **MySQL** services from the XAMPP control panel.</li>
        <li>Create the <code>sanroom</code> database and import the necessary SQL migrations.</li>
        <li>Verify and adjust DB connection credentials in the local **<code>database.php</code>** file.</li>
        <li>Access the application via your browser: <span style="font-weight: bold; color: #3498db;"><code>http://localhost/SanRoom/public/</code></span></li>
    </ol>
</section>

<hr style="border-top: 3px double #2ecc71; margin: 25px 0 0 0;">

<footer style="text-align: center; color: #7f8c8d; font-size: 0.9em; padding-top: 10px;">
    Developed with &#10084;&#65039; by the SanRoom Development Team | &copy; 2025
</footer>

</div>
