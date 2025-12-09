document.addEventListener("DOMContentLoaded", () => {
  // ----------------------- CONFIG -----------------------

  const BASE_URL = "/SANROOM/public/backend/";
  const STUDENT_ID = window.SANROOM_STUDENT_ID || null;

  // ----------------------- SHORTCUTS -----------------------

  const $ = (id) => document.getElementById(id);
  const schedulesGrid = $("schedulesGrid");
  const scheduleOverview = $("scheduleOverview");
  const studentSearch = $("studentSearch");
  const studentSort = $("studentSort");
  const joinForm = $("joinForm");
  const accessCodeInput = $("accessCodeInput");
  const joinBtn = $("joinBtn");
  const toastContainer = $("toastContainer");

  // MODAL SHORTCUTS (The ones you need for the missing logic)
  const messageModal = $("messageModal");
  const modalInstructorName = $("modalInstructorName");
  const modalClassName = $("modalClassName");
  const messageSubject = $("messageSubject");
  const messageBody = $("messageBody");
  const modalSendBtn = $("modalSendBtn");
  const modalCancelBtn = $("modalCancelBtn");

  // ----------------------- HELPERS ------------------------

  async function fetchJSON(url, options = {}) {
    const res = await fetch(url, options);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  }

  function capitalize(str) {
    return str ? str.replace(/\b\w/g, (c) => c.toUpperCase()) : "";
  }

  function showToast(msg, ok = true) {
    if (!toastContainer) return;

    const t = document.createElement("div");
    t.className = "toast " + (ok ? "success" : "error");
    t.textContent = msg;
    t.style.cssText = `
                background-color: ${ok ? "#22c55e" : "#ef4444"};
                color: white;
                padding: 10px 20px;
                border-radius: 4px;
                margin-top: 10px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                opacity: 0;
                transition: opacity 0.3s ease-in-out;
                margin-bottom: 5px;
            `;
    toastContainer.prepend(t);
    setTimeout(() => (t.style.opacity = 1), 10);
    setTimeout(() => (t.style.opacity = 0), 2700);
    setTimeout(() => t.remove(), 3000);
  }

  function toggleButtons(state) {
    if (joinBtn) {
      joinBtn.disabled = state;
      joinBtn.textContent = state ? "Joining..." : "Join Class";
    }
  }

  // ----------------------- RENDER LOGIC ------------------------

  function renderCard(s) {
    // --- Determine Card Status and Styling ---
    const scheduleStatus = (s.status ?? "active")
      .toLowerCase()
      .replace(/\s+/g, "-");
    const isFinished = scheduleStatus === "archived";
    const isSuspended = scheduleStatus === "suspended";
    const isDisabled = isFinished || isSuspended;
    const disabledAttribute = isDisabled ? "disabled" : "";

    let statusText = capitalize(s.status ?? "Active");
    let statusClass = scheduleStatus;
    let icon = '<span class="icon">📚</span>'; // Default icon for class

    // --- Room-only card logic (Styled) ---
    if (s.is_room_only) {
      statusText = "Room Only";
      statusClass = "room-only";
      icon = '<span class="icon">🚪</span>';

      return `
                <div class="room-card ${statusClass}">
                    <div class="card-header">
                        ${icon}
                        <h3 class="room-title">Room: ${
                          s.room_name ?? "N/A"
                        }</h3>
                        <div class="status-badge ${statusClass}">
                            ${statusText}
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="detail">Associated Access Code: <strong>${
                          s.special_access_code ?? "N/A"
                        }</strong></p>
                        <p class="detail secondary-info">
                            No active classes linked yet. You are associated with this physical space.
                        </p>
                    </div>
                    <div class="card-actions">
                        <button class="btn-leave-room btn-danger" data-room-id="${
                          s.roomId
                        }" title="Leave this room association">
                            <span class="icon">❌</span> Leave Room
                        </button>
                    </div>
                </div>
            `;
    }

    // --- Schedule card logic (Styled) ---
    if (isFinished) {
      icon = '<span class="icon">✅</span>';
      statusText = "Completed";
    } else if (isSuspended) {
      icon = '<span class="icon">⚠️</span>';
    } else if (scheduleStatus === "active") {
      icon = '<span class="icon">🟢</span>';
    }

    const scheduleId = s.id;
    const timeDetail = `Time: <strong>${s.day ?? "N/A"}</strong>, ${
      s.start_time ?? ""
    } - ${s.end_time ?? ""}`;
    const roomDetail = `Room: <strong>${s.room_name ?? "N/A"}</strong>`;

    return `
            <div class="room-card ${statusClass}">
                <div class="card-header">
                    ${icon}
                    <h3 class="room-title">${s.class_name ?? "N/A"} (${
      s.course_code ?? ""
    })</h3>
                    <div class="status-badge ${statusClass}">
                        ${statusText}
                    </div>
                </div>
                <div class="card-body">
                    <p class="detail instructor-detail">
                        Instructor: <strong>${
                          s.instructor_name ?? "Unknown"
                        }</strong>
                    </p>
                    <p class="detail room-detail">${roomDetail}</p>
                    <p class="detail time-detail">${timeDetail}</p>
                    ${
                      s.special_access_code
                        ? `<p class="detail code-detail">Access Code: <strong>${s.special_access_code}</strong></p>`
                        : ""
                    }
                </div>
                <div class="card-actions">
                    <button
                        class="btn-message btn-message-teacher"
                        data-schedule-id="${scheduleId}"
                        data-instructor-name="${s.instructor_name ?? "Teacher"}"
                        data-instructor-email="${s.instructor_email ?? ""}"
                        data-class-name="${s.class_name ?? "Class"}"
                        ${disabledAttribute}
                        title="Send a message to ${
                          s.instructor_name ?? "Teacher"
                        }"
                    >
                        <span class="icon">📧</span> Message Teacher
                    </button>
                    <button class="btn-leave btn-secondary" data-schedule-id="${scheduleId}" ${disabledAttribute}>
                        ${
                          isDisabled
                            ? "Completed"
                            : '<span class="icon">🚪</span> Leave Class'
                        }
                    </button>
                </div>
            </div>
        `;
  }

  // ----------------------- SEARCH & SORT ------------------------

  let allDashboardItems = [];

  function debounce(fn, wait) {
    let t = null;
    return function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), wait);
    };
  }

  function orderDay(a, b) {
    const order = [
      "Monday",
      "Tuesday",
      "Wednesday",
      "Thursday",
      "Friday",
      "Saturday",
      "Sunday",
    ];
    return order.indexOf(a || "") - order.indexOf(b || "");
  }

  function applySearchAndSort(list) {
    const term = (studentSearch?.value || "").trim().toLowerCase();

    let filtered = list.filter((s) => {
      if (!term) return true;

      const hay = `${s.class_name ?? ""} ${s.instructor_name ?? ""} ${
        s.room_name ?? ""
      } ${s.course_code ?? ""} ${s.special_access_code ?? ""}`.toLowerCase();

      return hay.includes(term);
    });

    const sortVal = studentSort?.value || "default";

    if (sortVal === "day" || sortVal === "default") {
      filtered.sort(
        (x, y) =>
          orderDay(x.day, y.day) ||
          (x.start_time ?? "").localeCompare(y.start_time ?? "")
      );
    } else if (sortVal === "start_time") {
      filtered.sort((x, y) =>
        (x.start_time ?? "").localeCompare(y.start_time ?? "")
      );
    } else if (sortVal === "class_name") {
      filtered.sort((x, y) =>
        (x.class_name ?? x.room_name ?? "").localeCompare(
          y.class_name ?? y.room_name ?? ""
        )
      );
    } else if (sortVal === "instructor_name") {
      filtered.sort((x, y) =>
        (x.instructor_name ?? "").localeCompare(y.instructor_name ?? "")
      );
    }

    return filtered;
  }

  function renderFilteredSchedules() {
    const itemsToRender = applySearchAndSort(allDashboardItems);

    schedulesGrid.innerHTML = "";

    const activeSchedulesCount = allDashboardItems.filter(
      (s) => !s.is_room_only
    ).length;
    const roomOnlyCount = allDashboardItems.filter(
      (s) => s.is_room_only
    ).length;

    scheduleOverview.textContent = `Active Classes: ${activeSchedulesCount} | Joined Rooms: ${roomOnlyCount}`;

    if (!itemsToRender || itemsToRender.length === 0) {
      schedulesGrid.innerHTML = allDashboardItems.length
        ? '<p class="loading-message">No matching items found.</p>'
        : '<p class="loading-message">You are not currently enrolled in any active classes or rooms. Use the access code above to join.</p>';

      return;
    }

    itemsToRender.forEach((s) => {
      schedulesGrid.insertAdjacentHTML("beforeend", renderCard(s));
    });
  }

  const debouncedRender = debounce(renderFilteredSchedules, 250);

  if (studentSearch) studentSearch.addEventListener("input", debouncedRender);
  if (studentSort)
    studentSort.addEventListener("change", renderFilteredSchedules);

  // ----------------------- LEAVE ACTIONS ------------------------

  async function handleLeaveClass(event) {
    if (!event.target.classList.contains("btn-leave") || event.target.disabled)
      return;

    const button = event.target;
    const scheduleId = button.getAttribute("data-schedule-id");

    if (
      !confirm(
        "Are you sure you want to leave this class? You will lose access to course materials."
      )
    )
      return;

    button.disabled = true;
    button.innerHTML = "Leaving...";

    try {
      const res = await fetchJSON(BASE_URL + "unenroll_student.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ schedule_id: scheduleId }),
      });

      if (res.success) {
        showToast(res.message || "Successfully left the class.", true);
        try {
          localStorage.setItem("schedulesUpdated", Date.now().toString());
        } catch (e) {}
        await loadStudentDashboardData(); // Call the combined loader
      } else {
        showToast(res.message || "Failed to leave the class.", false);
        button.innerHTML = '<span class="icon">🚪</span> Leave Class';
        button.disabled = false;
      }
    } catch (e) {
      console.error("Leave Class Error:", e);
      showToast(
        "A network or server error occurred. Please check your connection.",
        false
      );
      button.innerHTML = '<span class="icon">🚪</span> Leave Class';
      button.disabled = false;
    }
  }

  // NEW: Function to handle leaving a room (not a class/schedule)
  async function handleLeaveRoom(event) {
    if (
      !event.target.classList.contains("btn-leave-room") ||
      event.target.disabled
    )
      return;

    const button = event.target;
    const roomId = button.getAttribute("data-room-id");

    if (!confirm("Are you sure you want to leave this room association?"))
      return;

    button.disabled = true;
    button.innerHTML = "Leaving Room...";

    try {
      // Assumes unenroll_room.php exists
      const res = await fetchJSON(BASE_URL + "unenroll_room.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ room_id: roomId }),
      });

      if (res.success) {
        showToast(res.message || "Successfully left the room.", true);
        try {
          localStorage.setItem("schedulesUpdated", Date.now().toString());
        } catch (e) {}
        await loadStudentDashboardData(); // Reload the whole dashboard
      } else {
        showToast(res.message || "Failed to leave the room.", false);
      }
    } catch (e) {
      console.error("Leave Room Error:", e);
      showToast("A network or server error occurred.", false);
    } finally {
      button.innerHTML = '<span class="icon">❌</span> Leave Room';
      button.disabled = false;
    }
  }

  // ----------------------- MESSAGE TEACHER (MODAL LOGIC) ------------------------

  // ⚠️ MISSING FUNCTION DEFINITION ⚠️
  function closeModal() {
    if (messageModal) {
      messageModal.style.display = "none";
      document.body.style.overflow = ""; // Restore scrolling
      messageSubject.value = "";
      messageBody.value = "";
    }
  }

  // ⚠️ MISSING FUNCTION DEFINITION ⚠️
  async function handleSendMessage() {
    const email = modalSendBtn.getAttribute("data-instructor-email");
    const className = modalSendBtn.getAttribute("data-class-name");
    const subject = messageSubject.value.trim();
    const body = messageBody.value.trim();

    if (!subject || !body) {
      showToast("Please enter both a subject and a message.", false);
      return;
    }

    modalSendBtn.disabled = true;
    modalSendBtn.textContent = "Sending...";

    try {
      // --- REPLACE WITH YOUR ACTUAL BACKEND API CALL (e.g., send_message.php) ---
      await new Promise((resolve) => setTimeout(resolve, 500));
      console.log(
        `Sending message to ${email} about ${className}. Subject: ${subject}`
      );
      // Assuming the backend returns success: true
      showToast("Message sent successfully (simulated).", true);
      closeModal();
    } catch (e) {
      console.error("Send Message Error:", e);
      showToast("A network error occurred while sending the message.", false);
    } finally {
      modalSendBtn.disabled = false;
      modalSendBtn.textContent = "Send Message";
    }
  }

  // ⚠️ MISSING FUNCTION DEFINITION ⚠️
  function handleMessageTeacher(event) {
    if (
      !event.target.classList.contains("btn-message-teacher") ||
      event.target.disabled
    )
      return;

    if (!messageModal) {
      console.error("Message Modal element not found.");
      return;
    }

    const button = event.target;
    const instructorName = button.getAttribute("data-instructor-name");
    const instructorEmail = button.getAttribute("data-instructor-email");
    const className = button.getAttribute("data-class-name");

    modalSendBtn.setAttribute("data-instructor-email", instructorEmail);
    modalSendBtn.setAttribute("data-class-name", className);

    if (!instructorEmail || instructorEmail.trim() === "") {
      showToast(
        `Cannot message ${instructorName}: Email address not available.`,
        false
      );
      return;
    }

    // Populate modal fields
    modalInstructorName.textContent = instructorName;
    modalClassName.textContent = className;
    messageSubject.value = "";
    messageBody.value = "";

    // Display the modal
    messageModal.style.display = "flex";
    document.body.style.overflow = "hidden"; // Prevent scrolling background
  }

  // Universal listener for Card Actions
  schedulesGrid.addEventListener("click", (event) => {
    handleLeaveClass(event);
    // This line is now valid:
    handleMessageTeacher(event); // Triggers modal open

    if (event.target.classList.contains("btn-leave-room")) {
      handleLeaveRoom(event);
    }
  });

  // MODAL LISTENERS
  if (modalSendBtn) modalSendBtn.addEventListener("click", handleSendMessage);

  function handleModalClose(event) {
    if (
      event.target === messageModal ||
      event.target.getAttribute("data-action") === "close" ||
      event.target === modalCancelBtn
    ) {
      closeModal();
    }
  }

  if (messageModal) messageModal.addEventListener("click", handleModalClose);
  if (modalCancelBtn)
    modalCancelBtn.addEventListener("click", handleModalClose);

  // Close modal on Escape key press
  document.addEventListener("keydown", (event) => {
    if (
      event.key === "Escape" &&
      messageModal &&
      messageModal.style.display === "flex"
    ) {
      closeModal();
    }
  });

  // ----------------------- LOAD DATA ------------------------

  // NEW: Function to load room-only data
  async function loadStudentRoomsOnly() {
    try {
      const res = await fetchJSON(
        BASE_URL + `get_student_rooms_only.php?student_id=${STUDENT_ID}`
      );
      return Array.isArray(res.data) ? res.data : [];
    } catch (e) {
      console.error("Error loading room-only data:", e);
      return [];
    }
  }

  // REPLACED loadStudentSchedules: Combines schedule and room data
  async function loadStudentDashboardData() {
    if (!STUDENT_ID) return;

    toggleButtons(true);
    schedulesGrid.innerHTML =
      '<p class="loading-message">Fetching your schedules and rooms...</p>';

    try {
      // 1. Fetch Schedules
      const scheduleRes = await fetchJSON(
        BASE_URL + `get_student_schedules.php?student_id=${STUDENT_ID}`
      );
      let loadedSchedules = Array.isArray(scheduleRes.data)
        ? scheduleRes.data
        : [];

      const activeSchedules = loadedSchedules.filter(
        (s) => s.status?.toLowerCase() !== "archived"
      );

      // 2. Fetch Room-Only Entries
      const roomOnlyItems = await loadStudentRoomsOnly();

      // 3. Combine and Render
      allDashboardItems = [...activeSchedules, ...roomOnlyItems];

      renderFilteredSchedules();
    } catch (e) {
      console.error("Error loading dashboard data:", e);

      schedulesGrid.innerHTML =
        '<p class="loading-message" style="color:#ef4444;">Failed to load data. Please ensure you are logged in correctly.</p>';

      scheduleOverview.textContent = "Error loading data.";
    } finally {
      toggleButtons(false);
    }
  }

  // ----------------------- JOIN CLASS ------------------------

  joinForm?.addEventListener("submit", async (e) => {
    e.preventDefault();

    const accessCode = accessCodeInput.value.trim();

    if (!accessCode) {
      showToast("Please enter an access code.", false);
      return;
    }

    toggleButtons(true);

    try {
      const res = await fetchJSON(BASE_URL + "enroll_student.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ access_code: accessCode }),
      });

      showToast(res.message, res.success);

      if (res.success) {
        accessCodeInput.value = "";

        try {
          localStorage.setItem("schedulesUpdated", Date.now().toString());
        } catch (e) {}

        await loadStudentDashboardData(); // Call the combined loader
      }
    } catch (err) {
      console.error("Join Class Error:", err);
      showToast("An error occurred while attempting to join the class.", false);
    } finally {
      toggleButtons(false);
    }
  });

  // ----------------------- LEAVE BY CODE ------------------------
  document
    .getElementById("leaveByCodeBtn")
    ?.addEventListener("click", async (e) => {
      e.preventDefault();
      const code = accessCodeInput?.value?.trim();
      if (!code)
        return showToast("Please enter an access code to leave.", false);
      try {
        const res = await fetchJSON(BASE_URL + "unenroll_student.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ access_code: code }),
        });
        showToast(res.message, res.success);
        if (res.success) {
          try {
            localStorage.setItem("schedulesUpdated", Date.now().toString());
          } catch (e) {}
          accessCodeInput.value = "";
          await loadStudentDashboardData(); // Call the combined loader
        }
      } catch (err) {
        console.error("Leave By Code Error:", err);
        showToast("Failed to leave by code.", false);
      }
    });

  // ----------------------- INITIAL LOAD ------------------------
  if (STUDENT_ID) loadStudentDashboardData();
  else
    schedulesGrid.innerHTML =
      '<p class="loading-message" style="color:#ef4444;">Error: Student ID not found. Please log in.</p>';
});
