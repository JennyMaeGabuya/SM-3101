<?php
// student_dashboard.php

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) session_start();

// Include header and database connection
// NOTE: Adjust these paths if needed
include 'partials/header.php';
include __DIR__ . '/../database.php';

// Retrieve the logged-in student's ID (default to 100 for demo)
$student_id = intval($_SESSION['user_id'] ?? 100);
?>

<link rel="stylesheet" href="/SANROOM/public/assets/css/student_styles.css?v=2">
<div class="dashboard-container full-height">
    <main class="content">
        <h1 class="page-title">My Schedules</h1>
        <p class="page-description">
            Review your upcoming class schedules and use the access code to join a new course.
        </p>

        <div class="join-class-section">
            <div class="join-title">Join a New Course / Room</div>
            <form id="joinForm" class="join-form">
                <input type="text" id="accessCodeInput" placeholder="Enter Access Code" required>
                <button type="submit" id="joinBtn" class="btn-join">Join</button>
                <button type="button" id="leaveByCodeBtn" class="btn-leave-code">Leave</button>
            </form>
        </div>

        <div class="student-controls">
            <input type="text" id="studentSearch" placeholder="Search by class, instructor, room...">
            <select id="studentSort">
                <option value="default">Sort: Default (Day/Time)</option>
                <option value="day">Sort: Day</option>
                <option value="start_time">Sort: Start Time</option>
                <option value="class_name">Sort: Class Name</option>
                <option value="instructor_name">Sort: Instructor</option>
            </select>
        </div>

        <div id="statsBar" class="stats-bar">
            <div class="stats-text">
                <strong id="scheduleOverview">Loading schedule overview...</strong>
            </div>
        </div>

        <div id="schedulesGrid" class="rooms-grid">
            <p class="loading-message">Loading schedules...</p>
        </div>

        <div id="toastContainer" class="toast-container"></div>
    </main>
</div>

<div id="messageModal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Message Instructor</h3>
            <span class="close-btn" data-action="close">&times;</span>
        </div>
        <div class="modal-body">
            <p>You are sending a message to: <strong id="modalInstructorName"></strong></p>
            <p>Regarding class: <strong id="modalClassName"></strong></p>

            <label for="messageSubject">Subject:</label>
            <input type="text" id="messageSubject" class="modal-input" placeholder="e.g., Question about Assignment" required>

            <label for="messageBody">Message:</label>
            <textarea id="messageBody" class="modal-textarea" rows="6" placeholder="Type your message here..." required></textarea>
        </div>
        <div class="modal-footer">
            <button id="modalCancelBtn" class="btn-message" data-action="close">Cancel</button>
            <button id="modalSendBtn" class="btn-join">Send Message</button>
        </div>
    </div>
</div>
<script>
    window.SANROOM_STUDENT_ID = <?php echo json_encode($student_id, JSON_NUMERIC_CHECK); ?>;
</script>

<script src="/SANROOM/public/assets/js/student_dash.js?v=4"></script>