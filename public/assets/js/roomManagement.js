/**
 * SANROOM Room Management View (V3.0)
 * Separated View: Focuses ONLY on Room CRUD (Create, Read, Update/Status, Archive)
 * Schedule details and schedule actions are now handled in a separate module/view.
 */

document.addEventListener("DOMContentLoaded", () => {
  // ----------------------- 1. SHORTCUTS & STATE -----------------------
  const $ = (id) => document.getElementById(id);

  const roomsContainer = $("roomsContainer");
  const roomSummary = $("roomSummary");
  const roomFilter = $("roomFilter");
  const roomSort = $("roomSort");
  const addRoomForm = $("addRoomForm");
  const newRoomNameInput = $("newRoomName");
  const newRoomCapacityInput = $("newRoomCapacity");

  // <<<--- UPDATED SHORTCUTS FOR EDIT MODAL --->>>
  const editCapacityForm = $("editCapacityForm");
  const editRoomNameDisplay = $("editRoomNameDisplay");
  const editRoomIdInput = $("editRoomId");
  const editRoomNameInput = $("editRoomNameInput");
  const editRoomCapacityInput = $("editRoomCapacityInput");
  const editCapacityError = $("editCapacityError");
  // <<<--- END UPDATED SHORTCUTS --->>>

  // <<<--- REMOVED SHORTCUTS FOR INVITATION MODAL --->>>

  let roomsByName = {}; // State will now only hold room-centric data

  // ----------------------- 1.5 NEW API ENDPOINTS -----------------------
  const BASE_URL = "/SANROOM/public/backend/";
  const API_URL = BASE_URL + "get_all_rooms.php";
  const CREATE_ROOM_URL = BASE_URL + "create_new_room.php";
  const UPDATE_ROOM_STATUS_URL = BASE_URL + "update_room_status.php";
  const UPDATE_ROOM_CAPACITY_URL = BASE_URL + "update_room_capacity.php";
  const UPDATE_ROOM_DETAILS_URL = BASE_URL + "update_room_details.php";

  // **REMOVED API URLS FOR INVITATIONS:**

  // ----------------------- 2. CORE UTILITY FUNCTIONS -----------------------

  /**
   * @param {number} length
   * @returns {string} A random uppercase alphanumeric code.
   */
  function generateAccessCode(length = 6) {
    const characters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    let result = "";
    const charactersLength = characters.length;
    for (let i = 0; i < length; i++) {
      result += characters.charAt(Math.floor(Math.random() * charactersLength));
    }
    return result;
  }

  /**
   * NEW HELPER: Generates the code and displays it in the modal placeholder.
   */
  function showGeneratedCode() {
    const code = generateAccessCode();
    const displayElement = $("accessCodeDisplay");
    if (displayElement) {
      displayElement.textContent = code;
    }
  }

  // Reusable JSON fetch wrapper (No change)
  async function fetchJSON(url, options = {}) {
    const res = await fetch(url, options);
    const text = await res.text();
    try {
      const json = JSON.parse(text);
      const isDataFetch =
        options.method === undefined || options.method === "GET";

      if (isDataFetch) {
        // Simplified check, removed invitation specific check
        if (!json.success || !Array.isArray(json.data)) {
          throw new Error("Invalid API response format for data fetch.");
        }
        return json.data;
      } else {
        if (!json.success) {
          throw new Error(json.message || "Action failed on the server.");
        }
        return json;
      }
    } catch (e) {
      console.error("Fetch/Parse Error:", e, text);
      throw new Error(e.message || "Failed to process server response.");
    }
  }

  // Helper to inform other tabs/pages (No change)
  function triggerSchedulesUpdated() {
    try {
      localStorage.setItem("schedulesUpdated", Date.now().toString());
    } catch (e) {
      /* ignore localStorage error */
    }
  }

  // Modal Control Functions
  window.openModal = (id) => {
    $(id).style.display = "flex";
    // Generate and display code when Add Room modal opens
    if (id === "addRoomModal") {
      showGeneratedCode();
    }
  };
  window.closeModal = (id) => {
    $(id).style.display = "none";
  };

  // ----------------------- 3. DATA PROCESSING -----------------------

  /**
   * Build a room summary map from joined data.
   */
  function buildRoomsFromSchedules(data) {
    roomsByName = {};
    console.log("buildRoomsFromSchedules received:", data);

    data.forEach((item) => {
      // Skip hidden rooms
      if (item.is_hidden == 1) return;

      const name =
        (item.room_name && item.room_name.trim()) || "Unassigned Room";
      const capacity = parseInt(item.capacity ?? "0", 10) || 0;
      const manualStatus = item.manual_status || "available";
      const currentParticipants =
        parseInt(item.current_participants ?? "0", 10) || 0;
      const isScheduledOccupied = currentParticipants > 0;

      roomsByName[name] = {
        name,
        capacity,
        roomId: item.room_id,
        archived: item.is_archived == 1,
        occupied: manualStatus === "occupied",
        reserved: manualStatus === "reserved",
        accessCode: item.special_access_code,
        isScheduledOccupied,
        currentParticipants,
      };
    });
  }

  // Filter and Sort Logic (No change)
  function getFilteredAndSortedRooms() {
    const filterValue = roomFilter ? roomFilter.value : "all";
    const sortValue = roomSort ? roomSort.value : "name";

    let roomEntries = Object.values(roomsByName);

    roomEntries = roomEntries.filter((r) => {
      if (filterValue === "archived") {
        return r.archived;
      }
      return !r.archived;
    });

    if (filterValue !== "all" && filterValue !== "archived") {
      roomEntries = roomEntries.filter((r) => {
        const isScheduledOccupied = r.isScheduledOccupied;
        const isAvailable = !r.occupied && !r.reserved && !isScheduledOccupied;
        const isOccupiedManually = r.occupied && !r.reserved;

        if (filterValue === "reserved") return r.reserved;
        if (filterValue === "occupied")
          return isOccupiedManually || (isScheduledOccupied && !r.reserved);
        if (filterValue === "available") return isAvailable;
        return true;
      });
    }

    roomEntries.sort((a, b) => {
      if (sortValue === "name") {
        return a.name.localeCompare(b.name);
      } else if (sortValue === "capacity") {
        return b.capacity - a.capacity;
      } else if (sortValue === "availability") {
        const aStatusScore = a.reserved
          ? 3
          : a.occupied || a.isScheduledOccupied
          ? 2
          : 1;
        const bStatusScore = b.reserved
          ? 3
          : b.occupied || b.isScheduledOccupied
          ? 2
          : 1;
        return aStatusScore - bStatusScore;
      }
      return 0;
    });

    return roomEntries;
  }

  // ----------------------- 4. RENDER LOGIC -----------------------

  /**
   * Renders the entire view (list and summary). Called on load, filter/sort change, and API success.
   */
  function renderView() {
    updateRoomList();
    updateRoomSummary();
  }

  function updateRoomList() {
    roomsContainer.innerHTML = "";

    const roomEntries = getFilteredAndSortedRooms();

    if (!roomEntries.length) {
      roomsContainer.innerHTML =
        "<p class='empty'>No rooms match your criteria.</p>";
      return;
    }

    roomEntries.forEach((room) => {
      const isOccupied =
        room.occupied || room.isScheduledOccupied || room.reserved;

      let statusLabel = "Available";
      let statusClass = "available";
      let buttonsHTML = "";

      const {
        name,
        currentParticipants: scheduledCount,
        capacity,
        accessCode,
      } = room;

      const capacityText = capacity > 0 ? capacity : "Not set";
      const available =
        capacity > 0 ? Math.max(capacity - scheduledCount, 0) : "-";

      let toggleActionText = "Mark Occupied";
      if (room.reserved) {
        toggleActionText = "Clear Reservation";
      } else if (room.occupied) {
        toggleActionText = "Mark Available";
      }

      // --- START: Status and Button Logic (Cleaned up and moved to top) ---
      if (room.archived) {
        statusLabel = "Archived";
        statusClass = "archived";
        buttonsHTML = `<button class="btn-primary" data-action="unarchiveRoom">Unarchive</button>`;
      } else if (room.reserved) {
        statusLabel = "Reserved";
        statusClass = "reserved";
      } else if (isOccupied) {
        statusLabel = room.isScheduledOccupied ? "IN USE" : "Occupied";
        statusClass = "occupied";
      }

      if (!room.archived) {
        // Determine which secondary button should be shown for reserved status
        const reserveButton = room.reserved
          ? `<button class="btn-secondary btn-cancel-reserve" data-action="cancelReservation">Cancel Reservation</button>` // Modified button for reserved status
          : `<button class="btn-secondary btn-reserve" data-action="reserveRoom">Reserve for Event</button>`;

        buttonsHTML = `
                        <button class="btn-primary mark-toggle" data-action="toggleOccupied">${toggleActionText}</button>
                        ${reserveButton}
                        <button class="btn-info btn-edit-room" data-action="editCapacity">Edit</button>
                        <button class="btn-danger btn-archive-room" data-action="archiveRoom">Archive</button>
                    `;
      }
      // --- END: Status and Button Logic ---

      const card = document.createElement("div");
      card.className = `room-card-outer ${statusClass}`;
      card.dataset.roomName = name;

      // Prepare the access code for inline display
      const accessCodeDisplayInLine = accessCode
        ? ` &nbsp;|&nbsp; Special Code: <strong>${accessCode}</strong>`
        : "";

      // The content block: accessCodeDisplayInLine is now inside room-capacity
      card.innerHTML = `
                    <div class="room-info">
                        <h4 class="room-name">${name}</h4>
                        <p class="room-capacity">
                            Capacity: <strong>${capacityText}</strong> &nbsp;|&nbsp; 
                            Students: <strong>${scheduledCount}</strong> &nbsp;|&nbsp; 
                            Available: <strong>${available}</strong>
                            ${accessCodeDisplayInLine} 
                        </p>
                    </div>
                    <span class="room-status-badge ${statusClass}">
                        ${statusLabel}
                    </span>
                    <div class="room-card-footer">
                        ${buttonsHTML}
                    </div>
                `;

      // Bind events
      if (!room.archived) {
        card.querySelector(".mark-toggle").addEventListener("click", (e) => {
          e.stopPropagation();
          toggleRoomOccupied(name);
        });

        // Bind Reserve/Cancel button
        if (room.reserved) {
          // Reserved status: Show "Cancel Reservation" button
          card
            .querySelector(".btn-cancel-reserve")
            .addEventListener("click", (e) => {
              e.stopPropagation();
              reserveRoom(name); // reserveRoom function handles toggling to 'available'
            });
        } else {
          // Available/Occupied status: Show "Reserve for Event" button
          card.querySelector(".btn-reserve").addEventListener("click", (e) => {
            e.stopPropagation();
            reserveRoom(name);
          });
        }

        card.querySelector(".btn-edit-room").addEventListener("click", (e) => {
          e.stopPropagation();
          editRoomDetails(name); // Renamed function call
        });
        card
          .querySelector(".btn-archive-room")
          .addEventListener("click", (e) => {
            e.stopPropagation();
            archiveRoom(name);
          });
      } else {
        card.querySelector("button").addEventListener("click", (e) => {
          e.stopPropagation();
          unarchiveRoom(name);
        });
      }
      roomsContainer.appendChild(card);
    });
  }

  function updateRoomSummary() {
    const rooms = Object.values(roomsByName);
    const visibleRooms = rooms.filter((r) => !r.archived);

    const reservedRooms = visibleRooms.filter((r) => r.reserved).length;

    const occupiedRooms = visibleRooms.filter(
      (r) => r.occupied || r.isScheduledOccupied || r.reserved
    ).length;

    const archivedRooms = rooms.filter((r) => r.archived).length;
    const activeRooms = visibleRooms.length;
    const availableRooms = Math.max(activeRooms - occupiedRooms, 0);

    const totalParticipants = rooms.reduce(
      (sum, r) => sum + (r.currentParticipants || 0),
      0
    );

    console.log("updateRoomSummary:", {
      activeRooms,
      occupiedRooms,
      reservedRooms,
      availableRooms,
      archivedRooms,
      totalParticipants,
    });

    roomSummary.textContent = `Active: ${activeRooms} | Occupied: ${
      occupiedRooms - reservedRooms
    } | Reserved: ${reservedRooms} | Available: ${availableRooms} | Archived: ${archivedRooms} | Total Students: ${totalParticipants}`;
  }

  // ----------------------- 5. ROOM ACTIONS (Integrated API Calls) -----------------------

  // Centralized status update logic for the database (No change)
  async function updateRoomStatus(roomName, newStatus) {
    const room = roomsByName[roomName];
    if (!room || !room.roomId) {
      console.error(
        `Cannot update status: Room '${roomName}' not found or missing ID.`
      );
      return;
    }

    const statusToSend =
      newStatus === "archived"
        ? "archived"
        : newStatus === "available"
        ? "available"
        : newStatus;
    try {
      await fetchJSON(UPDATE_ROOM_STATUS_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ roomId: room.roomId, status: statusToSend }),
      });
      await loadRooms(); // Use the renamed function
      triggerSchedulesUpdated();
    } catch (error) {
      console.error(
        `Failed to update status for ${roomName}: ${error.message}`
      );
      alert(`Error updating room status: ${error.message}`);
    }
  }

  // CREATE ROOM function (sends access code) (No change)
  async function createNewRoom(event) {
    event.preventDefault();
    const name = newRoomNameInput.value.trim();
    const capacity = parseInt(newRoomCapacityInput.value, 10);
    const errorElement = $("addRoomError");
    errorElement.textContent = "";

    // Read the code generated in the openModal function
    const accessCode = $("accessCodeDisplay").textContent;

    if (!name || isNaN(capacity) || capacity <= 0) {
      errorElement.textContent =
        "Please enter a valid name and positive capacity.";
      return;
    }

    if (!accessCode || accessCode === "Code will be generated automatically.") {
      errorElement.textContent =
        "Access code generation failed. Please try again.";
      return;
    }

    try {
      await fetchJSON(CREATE_ROOM_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        // Include the access code in the request body
        body: JSON.stringify({
          roomName: name,
          capacity: capacity,
          specialAccessCode: accessCode,
        }),
      });

      newRoomNameInput.value = "";
      newRoomCapacityInput.value = "10";
      closeModal("addRoomModal");

      await loadRooms();
      triggerSchedulesUpdated();
    } catch (error) {
      errorElement.textContent = `Creation failed: ${error.message}`;
    }
  }

  function toggleRoomOccupied(roomName) {
    const room = roomsByName[roomName];
    if (!room || room.archived) return;

    let targetStatus;
    if (room.reserved) {
      targetStatus = "available";
    } else {
      targetStatus = room.occupied ? "available" : "occupied";
    }
    updateRoomStatus(roomName, targetStatus);
  }

  function reserveRoom(roomName) {
    const room = roomsByName[roomName];
    if (!room || room.archived) return;

    // If reserved, targetStatus is 'available' (to cancel the reservation)
    // If not reserved, targetStatus is 'reserved' (to set the reservation)
    const targetStatus = room.reserved ? "available" : "reserved";
    updateRoomStatus(roomName, targetStatus);
  }

  // Function to open the modal and populate all fields
  function editRoomDetails(roomName) {
    const room = roomsByName[roomName];
    if (!room || room.archived || !room.roomId) return;

    // Set modal values
    editRoomNameDisplay.textContent = `Edit Room: ${room.name}`;
    editRoomIdInput.value = room.roomId;
    editRoomNameInput.value = room.name;
    editRoomCapacityInput.value = room.capacity;
    editCapacityError.textContent = "";

    openModal("editCapacityModal");
  }

  // Function to handle form submission for all room details
  async function saveRoomDetails(event) {
    event.preventDefault();

    const roomId = editRoomIdInput.value;
    const newName = editRoomNameInput.value.trim();
    const newCap = parseInt(editRoomCapacityInput.value, 10);

    editCapacityError.textContent = "";

    if (!newName) {
      editCapacityError.textContent = "Room name cannot be empty.";
      return;
    }
    if (isNaN(newCap) || newCap < 0) {
      editCapacityError.textContent =
        "Please enter a valid non-negative number for capacity.";
      return;
    }

    try {
      // POST to the new URL with both name and capacity
      await fetchJSON(UPDATE_ROOM_DETAILS_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          roomId: roomId,
          roomName: newName,
          capacity: newCap,
        }),
      });

      closeModal("editCapacityModal");
      await loadRooms();
      triggerSchedulesUpdated();
    } catch (error) {
      editCapacityError.textContent = `Update failed: ${error.message}`;
    }
  }

  // ----------------------- 5.5 INVITATION MANAGEMENT LOGIC (REMOVED) -----------------------
  // The openManageInvitationModal and saveInvitationList functions were removed.

  // --- HYPOTHETICAL MODAL FUNCTION (Replaces default confirm()) ---
  /**
   * Shows a custom styled confirmation modal. (No change)
   * @param {string} title - The title of the modal (e.g., 'Confirm Archiving').
   * @param {string} message - The confirmation message body.
   * @param {function} confirmCallback - The function to execute if the user confirms.
   * @param {boolean} isDestructive - True if the action is destructive (Archive, Delete).
   */
  function showConfirmationModal(
    title,
    message,
    confirmCallback,
    isDestructive = false
  ) {
    const modal = document.getElementById("confirmationModal");
    if (!modal) {
      console.error(
        "Confirmation modal element with ID 'confirmationModal' not found."
      );
      // Fallback to native confirm if the custom modal isn't available
      if (
        confirm(
          `Action: ${title}\nMessage: ${message.replace(
            /\*\*|\\n/g,
            ""
          )}\nProceed?`
        )
      ) {
        confirmCallback();
      }
      return;
    }

    const modalTitle = modal.querySelector("#confirmModalTitle");
    const modalMessage = modal.querySelector("#confirmModalMessage");
    const confirmButton = modal.querySelector("#confirmModalButton");
    const cancelButton = modal.querySelector("#cancelModalButton");
    const closeBtn = modal.querySelector(".close-btn");

    // 1. Set text content
    modalTitle.textContent = title;
    // Set message, replacing Markdown bolding with HTML <strong> for styling
    modalMessage.innerHTML = message.replace(
      /\*\*(.*?)\*\*/g,
      "<strong>$1</strong>"
    );

    // 2. Reset and apply button styling based on action type
    confirmButton.textContent = isDestructive ? "Archive" : "Confirm"; // Changed for clarity
    confirmButton.className = "btn-confirm-action"; // Keep base class
    confirmButton.classList.add(
      isDestructive ? "btn-confirm-archive" : "btn-confirm-default"
    );

    // 3. Clear and set new event listener for the confirm button
    // Clone node to safely remove all previous listeners and prevent stacking
    const newConfirmButton = confirmButton.cloneNode(true);
    confirmButton.parentNode.replaceChild(newConfirmButton, confirmButton);

    newConfirmButton.addEventListener(
      "click",
      () => {
        // Hide modal
        modal.style.display = "none";
        // Execute the action
        confirmCallback();
      },
      { once: true }
    );

    // 4. Set event listeners for cancel button and close icon (closes modal)
    const hideModal = () => {
      modal.style.display = "none";
    };
    cancelButton.onclick = hideModal;
    closeBtn.onclick = hideModal;

    // 5. Show the modal
    modal.style.display = "flex";
  }

  // --- UPDATED ROOM FUNCTIONS (No change) ---

  function archiveRoom(roomName) {
    const title = `Confirm Archiving Room ${roomName}`;
    const message = `Are you sure you want to **archive** room "${roomName}"? This action will set its status to archived and hide it from the active list.`;

    // Call the custom modal function
    showConfirmationModal(
      title,
      message,
      () => updateRoomStatus(roomName, "archived"), // Action on confirmation
      true // Destructive (Archive)
    );
  }

  function unarchiveRoom(roomName) {
    const title = `Confirm Unarchiving Room ${roomName}`;
    const message = `Are you sure you want to **unarchive** room "${roomName}"? It will return to the active list with an **available** status.`;

    // Call the custom modal function
    showConfirmationModal(
      title,
      message,
      () => updateRoomStatus(roomName, "available"), // Action on confirmation
      false // Non-destructive (Unarchive)
    );
  }

  // ----------------------- 6. INITIALIZATION & LIVE UPDATES -----------------------

  // Renamed function from loadSchedules to loadRooms for semantic clarity (No change)
  async function loadRooms() {
    roomsContainer.innerHTML = "<p>Loading room data...</p>";
    try {
      console.log("loadRooms: Fetching from " + API_URL);
      const roomData = await fetchJSON(API_URL);
      console.log("loadRooms: Received data:", roomData);
      buildRoomsFromSchedules(roomData);
      renderView();
    } catch (error) {
      console.error("loadRooms error:", error);
      roomsContainer.innerHTML = `<p class='error-message'>Error loading room data: ${error.message}</p>`;
    }
  }

  window.addEventListener("storage", (e) => {
    if (e.key === "schedulesUpdated") {
      console.log(
        "Detected schedulesUpdated event, reloading rooms immediately..."
      );
      loadRooms();
    }
  });

  // Aggressive polling: check every 2 seconds for real-time updates
  // NOTE: In a production environment, consider reducing this interval (e.g., 10-30s) or using WebSockets.
  setInterval(() => {
    console.log("Poll tick - refreshing room data...");
    loadRooms();
  }, 2000);

  if (addRoomForm) {
    addRoomForm.addEventListener("submit", createNewRoom);
  }

  // Listener for the Edit Capacity Form
  if (editCapacityForm) {
    editCapacityForm.addEventListener("submit", saveRoomDetails);
  }

  // *** REMOVED: Listener for the Invitation Management Form ***

  if (roomFilter) roomFilter.addEventListener("change", renderView);
  if (roomSort) roomSort.addEventListener("change", renderView);

  // Call the renamed function at startup
  loadRooms();
});
