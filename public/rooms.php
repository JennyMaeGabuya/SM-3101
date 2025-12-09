<?php include 'partials/header.php'; ?>

<div class="dashboard-container">
  <?php include 'partials/sidebar.php'; ?>

  <link rel="stylesheet" href="assets/css/room.css">

  <div id="toastContainer"></div>

  <main class="dashboard-content">

    <div class="top-bar">
      <div>
        <h2>Room Management</h2>
        <p>Overview of rooms and their scheduled usage</p>
      </div>
    </div>

    <section class="room-list">

      <div class="room-control-bar">
        <div class="filters">
          <label for="roomFilter">Filter Status:</label>
          <select id="roomFilter" onchange="renderView()">
            <option value="all">All Statuses</option>
            <option value="available">Available</option>
            <option value="occupied">Occupied</option>
            <option value="reserved">Reserved for Event</option>
            <option value="archived">Archived Rooms</option>
          </select>

          <label for="roomSort">Sort By:</label>
          <select id="roomSort" onchange="renderView()">
            <option value="name">Name (A-Z)</option>
            <option value="capacity">Capacity (High-Low)</option>
            <option value="availability">Availability</option>
          </select>
        </div>

        <button id="addRoomBtn" class="btn-action-primary" onclick="openModal('addRoomModal')">
          + Add New Room
        </button>
      </div>

      <h3>All Rooms</h3>
      <p id="roomSummary">Active: 0 | Occupied: 0 | Reserved: 0 | Available: 0 | Archived: 0</p>

      <div class="rooms-container" id="roomsContainer">
      </div>
    </section>

    <div id="addRoomModal" class="modal">
      <div class="modal-content">
        <span class="close-btn" onclick="closeModal('addRoomModal')">&times;</span>
        <h3>Add New Room</h3>
        <form id="addRoomForm">
          <label for="newRoomName">Room Name:</label>
          <input type="text" id="newRoomName" required>

          <label for="newRoomCapacity">Capacity:</label>
          <input type="number" id="newRoomCapacity" min="1" value="10" required>

          <div class="access-code-placeholder">
            <label>Join Code:</label>
            <span id="accessCodeDisplay" class="code-display">Code will be generated automatically.</span>
          </div>

          <button type="submit" class="btn-primary">Create Room</button>

          <p id="addRoomError" class="error-message" style="color: red;"></p>
        </form>
      </div>
    </div>

    <div id="scheduleDetailModal" class="modal">
      <div class="modal-content">
        <span class="close-btn" onclick="closeModal('scheduleDetailModal')">&times;</span>
        <h3 id="scheduleRoomName">Room Detail</h3>
        <p class="modal-status-badge" id="scheduleRoomStatus"></p>

        <h4>Active Schedules</h4>
        <div id="activeScheduleList" class="schedule-list">
          <p>Click on an occupied room to see schedules.</p>
        </div>

        <button class="btn-action-secondary" onclick="closeModal('scheduleDetailModal')">Close</button>
      </div>
    </div>

    <div id="editCapacityModal" class="modal">
      <div class="modal-content">
        <span class="close-btn" onclick="closeModal('editCapacityModal')">&times;</span>
        <h3 id="editRoomNameDisplay">Edit Room Details</h3>
        <form id="editCapacityForm">
          <input type="hidden" id="editRoomId" value="">

          <label for="editRoomNameInput">Room Name:</label>
          <input type="text" id="editRoomNameInput" required>

          <label for="editRoomCapacityInput">Capacity:</label>
          <input type="number" id="editRoomCapacityInput" min="1" required>

          <p id="editCapacityError" class="error-message" style="color: red;"></p>

          <button type="submit" class="btn-primary">Save Changes</button>
        </form>
      </div>
    </div>

    <div id="manageInvitationModal" class="modal">
      <div class="modal-content">
        <span class="close-btn" onclick="closeModal('manageInvitationModal')">&times;</span>
        <h3 id="invitationRoomNameDisplay">Manage Room Invitations</h3>
        <p>Enter the **email addresses** of students exclusively invited to this event. Enter one email per line.</p>

        <form id="manageInvitationForm">
          <input type="hidden" id="invitationRoomId" value="">

          <label for="invitedEmailsInput">Invited Student Emails:</label>
          <textarea id="invitedEmailsInput" rows="10" placeholder="student.a@school.edu&#10;student.b@school.edu&#10;..."></textarea>

          <p id="invitationError" class="error-message" style="color: red;"></p>

          <button type="submit" class="btn-primary">Save Invitation List</button>
          <button type="button" class="btn-cancel" onclick="closeModal('manageInvitationModal')">Cancel</button>
        </form>
      </div>
    </div>
    <div id="confirmationModal" class="modal confirmation-modal">
      <div class="modal-content">
        <h3 id="confirmModalTitle"></h3>
        <span class="close-btn" onclick="closeModal('confirmationModal')">&times;</span>

        <p id="confirmModalMessage"></p>

        <div class="confirmation-button-group">
          <button id="cancelModalButton" class="btn-cancel" onclick="closeModal('confirmationModal')">Cancel</button>
          <button id="confirmModalButton" class="btn-confirm-action"></button>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="assets/js/roomManagement.js?v=6"></script>
<script src="assets/js/sidebar.js?v=2"></script>