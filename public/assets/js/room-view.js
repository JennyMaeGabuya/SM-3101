/**
 * SANROOM Room Management View
 * V1.2: Added "Reserve for Event" functionality and unique classes for action buttons.
 */

document.addEventListener("DOMContentLoaded", () => {
  // ----------------------- 1. SHORTCUTS & STATE -----------------------
  const $ = (id) => document.getElementById(id); // DOM Elements

  const roomsContainer = $("roomsContainer");
  const roomSummary = $("roomSummary"); // State Variables

  let allSchedules = []; // Added 'reserved' status to the room state
  let roomsByName = {}; // roomName -> {name, capacity, schedules, archived, occupied, reserved, currentParticipants}

  const API_URL = "/SANROOM/public/backend/get_all_schedules.php"; // ----------------------- 2. CORE UTILITY FUNCTIONS -----------------------
  /**
   * Reusable JSON fetch wrapper.
   * @param {string} url
   * @returns {Promise<Object>}
   */

  async function fetchJSON(url) {
    const res = await fetch(url);
    const text = await res.text();
    try {
      const json = JSON.parse(text);
      if (!json.success || !Array.isArray(json.data)) {
        throw new Error("Invalid API response format.");
      }
      return json.data;
    } catch (e) {
      console.error("Fetch/Parse Error:", e, text);
      throw new Error("Failed to process server response.");
    }
  } // ----------------------- 3. DATA PROCESSING ----------------------- // Build a room summary map from schedules

  function buildRoomsFromSchedules(schedules) {
    roomsByName = {};

    schedules.forEach((s) => {
      // Use fallback room name if room is empty
      const name =
        (r.room_name.room && r.room_name.trim()) || "Unassigned Room";

      if (!roomsByName[name]) {
        const capacity = parseInt(r.capacity ?? "0", 10) || 0;

        roomsByName[name] = {
          name,
          capacity,
          schedules: [],
          archived: false,
          occupied: false, // Manual status
          reserved: false, // NEW: Event reservation status
          currentParticipants: 0,
        };
      } // Normalize and track participants

      s.participant_count = parseInt(s.participant_count ?? 0, 10) || 0;
      roomsByName[name].schedules.push(s); // Only count participants for non-archived schedules

      if ((s.status ?? "") !== "archived") {
        roomsByName[name].currentParticipants += s.participant_count;
      }
    });
  } // ----------------------- 4. RENDER LOGIC ----------------------- // Render modern room cards (one per room)

  function updateRoomList() {
    roomsContainer.innerHTML = ""; // Only show rooms that haven't been locally archived

    const roomEntries = Object.values(roomsByName).filter((r) => !r.archived);

    if (!roomEntries.length) {
      roomsContainer.innerHTML = "<p class='empty'>No rooms available.</p>";
      return;
    }

    roomEntries.forEach((room) => {
      const activeSchedules = room.schedules.filter(
        (s) => s.status !== "archived"
      ); // A room is considered occupied if it has active schedules, is manually occupied, OR is reserved

      const isOccupied =
        room.occupied || activeSchedules.length > 0 || room.reserved;
      let statusLabel = "Available";
      let statusClass = "available";

      if (room.reserved) {
        statusLabel = "Reserved";
        statusClass = "reserved";
      } else if (isOccupied) {
        statusLabel = "Occupied";
        statusClass = "occupied";
      }

      const { name, currentParticipants: joined, capacity } = room;

      const capacityText = capacity > 0 ? capacity : "Not set";
      const available = capacity > 0 ? Math.max(capacity - joined, 0) : "-";

      const card = document.createElement("div");
      card.className = `room-card-outer ${statusClass}`;
      card.dataset.roomName = name; // Determine the appropriate action button text based on current status

      let toggleActionText = "Mark Occupied";
      if (room.reserved) {
        toggleActionText = "Clear Reservation";
      } else if (room.occupied) {
        toggleActionText = "Mark Available";
      }

      card.innerHTML = `
                <div class="room-card-header-row">
                    <div>
                        <h4 class="room-name">${name}</h4>
                        <p class="room-capacity">
                            Capacity: <strong>${capacityText}</strong> &nbsp;|&nbsp; 
                            Joined: <strong>${joined}</strong> &nbsp;|&nbsp; 
                            Available: <strong>${available}</strong>
                        </p>
                    </div>
                    <span class="room-status-badge ${statusClass}">
                        ${statusLabel}
                    </span>
                </div>
                <div class="room-card-footer">
                    <button class="btn-primary mark-toggle" data-action="toggleOccupied">${toggleActionText}</button>
                    <button class="btn-secondary btn-reserve" data-action="reserveRoom">${
        room.reserved ? "Cancel Reserve" : "Reserve for Event"
      }</button>
                    <button class="btn-info btn-edit-room" data-action="editCapacity">Edit</button>
                    <button class="btn-danger btn-archive-room" data-action="archiveRoom">Archive</button>
                </div>
            `; // Bind events (using the existing functions)

      card
        .querySelector(".mark-toggle")
        .addEventListener("click", () => toggleRoomOccupied(name));
      card
        .querySelector(".btn-reserve")
        .addEventListener("click", () => reserveRoom(name));
      card
        .querySelector(".btn-edit-room")
        .addEventListener("click", () => editRoomCapacity(name));
      card
        .querySelector(".btn-archive-room")
        .addEventListener("click", () => archiveRoom(name));

      roomsContainer.appendChild(card);
    });
  } // Simple summary based on rooms + schedules (matching header text)

  function updateRoomSummary() {
    const rooms = Object.values(roomsByName);
    const visibleRooms = rooms.filter((r) => !r.archived);

    const occupiedRooms = visibleRooms.filter(
      (r) =>
        r.occupied ||
        r.schedules.some((s) => s.status !== "archived") ||
        r.reserved
    ).length;

    const archivedRooms = rooms.filter((r) => r.archived).length;
    const activeRooms = visibleRooms.length;
    const availableRooms = Math.max(activeRooms - occupiedRooms, 0);
    const reservedRooms = visibleRooms.filter((r) => r.reserved).length; // New count

    roomSummary.textContent = `Active: ${activeRooms} | Occupied: ${
      occupiedRooms - reservedRooms
    } | Reserved: ${reservedRooms} | Available: ${availableRooms} | Archived: ${archivedRooms}`;
  } // Master render function

  function renderView() {
    buildRoomsFromSchedules(allSchedules);
    updateRoomList();
    updateRoomSummary();
  } // ----------------------- 5. ROOM ACTIONS (Modified) -----------------------

  function toggleRoomOccupied(roomName) {
    const room = roomsByName[roomName];
    if (!room) return; // Clear reservation if present

    if (room.reserved) {
      room.reserved = false;
      room.occupied = false; // Reset occupied status too
    } else {
      // Toggle manual occupied status
      room.occupied = !room.occupied;
    }
    renderView();
  }

  function reserveRoom(roomName) {
    const room = roomsByName[roomName];
    if (!room) return; // Toggle the reserved status

    room.reserved = !room.reserved; // If reserving, ensure occupied is false (Reservation overrides simple occupied status)

    if (room.reserved) {
      room.occupied = false;
    }
    renderView();
  }

  function editRoomCapacity(roomName) {
    const room = roomsByName[roomName];
    if (!room) return;

    const newCapStr = prompt(
      `Set capacity for ${room.name}:`,
      String(room.capacity)
    );
    if (newCapStr === null) return;

    const newCap = parseInt(newCapStr, 10); // Allow capacity 0 if needed, but not negative or non-numeric
    if (isNaN(newCap) || newCap < 0) {
      alert("Please enter a valid non-negative number for capacity.");
      return;
    }

    room.capacity = newCap;
    renderView();
  }

  function archiveRoom(roomName) {
    const room = roomsByName[roomName];
    if (!room) return;

    if (
      !confirm(
        `Archive room "${room.name}" from this view? This only hides it locally.`
      )
    )
      return;
    room.archived = true;
    renderView();
  } // ----------------------- 6. INITIALIZATION & LIVE UPDATES ----------------------- // Fetch all schedules once and then filter client-side

  async function loadSchedules() {
    roomsContainer.innerHTML = "<p>Loading room data...</p>";
    try {
      allSchedules = await fetchJSON(API_URL);
      renderView();
    } catch (error) {
      roomsContainer.innerHTML = "<p>Error loading room data.</p>";
    }
  } // Listen for schedule changes from other tabs/pages (dashboard) via localStorage

  window.addEventListener("storage", (e) => {
    if (e.key === "schedulesUpdated") {
      console.log("Detected schedulesUpdated event, reloading schedules...");
      loadSchedules();
    }
  }); // Initial load

  loadSchedules();
});
