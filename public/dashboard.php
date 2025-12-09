<?php
include 'partials/header.php';
include __DIR__ . "/../database.php";
?>

<link rel="stylesheet" href="/SANROOM/public/assets/css/dashboard.css?v=2">

<div class="dashboard-container">

  <?php include 'partials/sidebar.php'; ?>

  <div id="toastContainer"></div>

  <main class="dashboard-content">
    <div class="top-bar">
      <h2>Schedule Management</h2>
      <p>Add, edit or delete class schedules</p>
      <div class="action-buttons">
        <button class="btn-view" id="toggleArchiveBtn" data-view="active">View Archived</button>
        <button class="btn-add" id="addScheduleBtn">+ Add Schedule</button>
      </div>
    </div>

    <section class="schedule-list" id="archivedSection" style="display:none;">
      <h3>Archived Schedules</h3>
      <div id="archivedList"></div>
    </section>

    <section class="schedule-list" id="activeSection" style="display:block;">
      <h3>Active Schedules</h3>
      <div id="activeList"></div>
    </section>

  </main>
</div>

<div class="modal" id="scheduleModal">
  <div class="modal-content">
    <span class="close-btn">&times;</span>
    <h3 id="modalTitle">Add New Schedule</h3>

    <form id="scheduleForm" enctype="multipart/form-data">
      <input type="hidden" id="scheduleId">

      <div class="form-row">
        <div class="form-group">
          <label for="className">Class Name</label>
          <input type="text" id="className" placeholder="e.g., Programming 101" required>
        </div>

        <div class="form-group">
          <label for="courseCode">Course Code</label>
          <input type="text" id="courseCode" placeholder="e.g., IT-405" required>
        </div>
      </div>

      <div class="form-group">
        <label for="instructor">Instructor Name</label>
        <input type="text" id="instructor" placeholder="e.g., Prof. Juan Dela Cruz" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="instructorEmail">Instructor Email</label>
          <input type="email" id="instructorEmail" placeholder="email@example.com" required>
        </div>

        <div class="form-group">
          <label for="instructorPhone">Instructor Phone</label>
          <input type="tel" id="instructorPhone" placeholder="09xxxxxxxxx" required>
        </div>
      </div>

      <div class="form-group">
        <label for="instructorImage">Instructor Image</label>
        <input type="file" id="instructorImage" accept="image/*">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="room">Room / Lab</label>
          <select id="room" required>
            <option value="">Select a room</option>
            <option value="201" data-name="CICS 201">CICS 201</option>
            <option value="202" data-name="CICS 202">CICS 202</option>
            <option value="203" data-name="CICS 203">CICS 203</option>
            <option value="204" data-name="CICS 204">CICS 204</option>
            <option value="301" data-name="CICS 301">CICS 301</option>
            <option value="302" data-name="CICS 302">CICS 302</option>
            <option value="303" data-name="CICS 303">CICS 303</option>
            <option value="304" data-name="CICS 304">CICS 304</option>
            <option value="401" data-name="CICS 401">CICS 401</option>
            <option value="402" data-name="CICS 402">CICS 402</option>
            <option value="403" data-name="CICS 403">CICS 403</option>
            <option value="404" data-name="CICS 404">CICS 404</option>
            <option value="1001" data-name="CICS NEW">NEW COMPLAB</option>
            <option value="1002" data-name="CICS OLD">OLD COMPLAB</option>
          </select>
        </div>

        <div class="form-group">
          <label for="roomCapacity">Maximum Capacity</label>
          <input type="number" id="roomCapacity" placeholder="e.g., 40" min="1" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="day">Day</label>
          <select id="day" required>
            <option>Monday</option>
            <option>Tuesday</option>
            <option>Wednesday</option>
            <option>Thursday</option>
            <option>Friday</option>
            <option>Saturday</option>
            <option>Sunday</option>
          </select>
        </div>

        <div class="form-group">
          <label for="joinCode">Join Code</label>
          <input type="text" id="joinCode" placeholder="e.g., G00GLE-M33T" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="startTime">Start Time</label>
          <input type="time" id="startTime" required>
        </div>

        <div class="form-group">
          <label for="endTime">End Time</label>
          <input type="time" id="endTime" required>
        </div>
      </div>
      <div id="studentListContainer" class="form-group" style="margin-top: 20px;">
      </div>

      <div class="modal-buttons">
        <button type="button" class="btn-cancel">Cancel</button>
        <button type="submit" class="btn-save">Save</button>
      </div>
    </form>
  </div>
</div>

<script src="/SANROOM/public/assets/js/dashboard.js?v=2"></script>