// Clean, working, optimized dashboard JS
const BASE_URL = "/SANROOM/public/backend/";

document.addEventListener("DOMContentLoaded", () => {
  // ----------------------- SHORTCUTS -----------------------
  const $ = (id) => document.getElementById(id);

  const modal = $("scheduleModal");
  const form = $("scheduleForm");
  const toggleArchiveBtn = $("toggleArchiveBtn");
  const archivedSection = $("archivedSection");
  const activeSection = $("activeSection");
  const archivedList = $("archivedList");
  const activeList = $("activeList");

  const inputs = {
    id: $("scheduleId"),
    className: $("className"),
    instructor: $("instructor"),
    instructorEmail: $("instructorEmail"),
    instructorPhone: $("instructorPhone"),
    instructorImage: $("instructorImage"),
    courseCode: $("courseCode"),
    joinCode: $("joinCode"), // <-- Ensure your HTML element has id="joinCode"
    room: $("room"),
    // INPUT FIELD: Room Capacity (Maximum Capacity)
    roomCapacity: $("roomCapacity"),
    day: $("day"),
    startTime: $("startTime"), // <-- Ensure your HTML element has id="startTime"
    endTime: $("endTime"), // <-- Ensure your HTML element has id="endTime"
  };

  // ELEMENT: Student list container (for modal display)
  const studentListContainer = $("studentListContainer");

  let schedules = { active: [], archived: [] };
  let currentView = "active";
  let isModalOpen = false;

  // ----------------------- MODAL CONTROL ------------------------

  function toggleModal(state) {
    if (!modal) return;
    modal.classList.toggle("visible", state);
    isModalOpen = state;
  }

  function resetForm() {
    if (!form) return;
    form.reset();
    if (inputs.id) inputs.id.value = "";
    $("modalTitle").textContent = "Add New Schedule";
    // Clear student list when opening for a new schedule
    if (studentListContainer) studentListContainer.innerHTML = "";
  }

  // Open modal only on button click
  $("addScheduleBtn")?.addEventListener("click", () => {
    if (isModalOpen) return;
    resetForm();
    toggleModal(true);
  });

  // Close modal on cancel/close buttons
  modal
    ?.querySelectorAll(".close-btn, .btn-cancel")
    .forEach((b) => b.addEventListener("click", () => toggleModal(false)));

  // Prevent modal from showing when navigating back via browser cache
  window.addEventListener("pageshow", (event) => {
    if (event.persisted && modal) {
      toggleModal(false);
    }
  });

  // ----------------------- ROOM INPUT HANDLER ------------------------

  // Fetch students instantly when the room input changes in the modal
  inputs.room?.addEventListener("change", (e) => {
    const roomId = e.target.value;
    if (roomId) {
      // Fetch students instantly to reflect enrollment changes
      fetchStudentsForRoom(roomId);
    } else {
      if (studentListContainer) studentListContainer.innerHTML = "";
    }
  });

  // ----------------------- FORM SUBMISSION ------------------------

  form?.addEventListener("submit", async (e) => {
    e.preventDefault();

    // 1. Initial Validation Check (Ensures Time Inputs aren't empty)
    // We check the physical input elements here.
    if (!inputs.startTime?.value || !inputs.endTime?.value) {
      // Trigger the browser's native HTML5 validation
      if (inputs.startTime && !inputs.startTime.value) {
        inputs.startTime.reportValidity();
      } else if (inputs.endTime && !inputs.endTime.value) {
        inputs.endTime.reportValidity();
      }
      showToast("Please ensure Start Time and End Time are set.", false);
      return;
    }

    showLoading(true);

    const fd = new FormData();

    const startVal = inputs.startTime.value; // Now guaranteed to have a value
    const endVal = inputs.endTime.value; // Now guaranteed to have a value

    // 2. Time Logic Check
    if (startVal >= endVal) {
      showToast("End time must be later than start time.", false);
      showLoading(false);
      return;
    }

    if (inputs.id && inputs.id.value) fd.append("id", inputs.id.value);
    if (inputs.className)
      fd.append("class_name", (inputs.className.value || "").trim());
    if (inputs.instructor)
      fd.append("instructor_name", (inputs.instructor.value || "").trim());
    if (inputs.instructorEmail)
      fd.append(
        "instructor_email",
        (inputs.instructorEmail.value || "").trim()
      );
    if (inputs.instructorPhone)
      fd.append(
        "instructor_phone",
        (inputs.instructorPhone.value || "").trim()
      );
    if (inputs.instructorImage?.files?.length)
      fd.append("instructor_image", inputs.instructorImage.files[0]);
    if (inputs.courseCode)
      fd.append("course_code", (inputs.courseCode.value || "").trim());

    // 🚨 CRITICAL: Ensure joinCode value is trimmed before sending
    if (inputs.joinCode)
      fd.append("join_code", (inputs.joinCode.value || "").trim());

    if (inputs.room) {
      const selectedOption = inputs.room.selectedOptions[0];
      fd.append("room_id", selectedOption.value ?? "");
      fd.append("room_name", selectedOption.dataset.name ?? "");
    }

    // APPEND NEW FIELD: room_capacity
    if (inputs.roomCapacity)
      fd.append("room_capacity", inputs.roomCapacity.value ?? "");
    if (inputs.day) fd.append("day", inputs.day.value ?? "");

    // Append time values (24-hour format from the input field)
    fd.append("start_time", startVal);
    fd.append("end_time", endVal);

    try {
      const res = await fetchJSON(BASE_URL + "save_schedule.php", {
        method: "POST",
        body: fd,
      });

      showToast(res.message, res.success);

      if (res.success) {
        toggleModal(false);
        await loadActiveSchedules();
        try {
          localStorage.setItem("schedulesUpdated", Date.now().toString());
        } catch (e) {}
      }
    } catch (err) {
      console.error(err);
      showToast("Error saving schedule.", false);
    } finally {
      showLoading(false);
    }
  });

  // ----------------------- VIEW SWITCH ------------------------

  toggleArchiveBtn?.addEventListener("click", () => {
    if (currentView === "active") {
      activeSection.style.display = "none";
      archivedSection.style.display = "block";
      toggleArchiveBtn.textContent = "View Active";
      currentView = "archived";
      loadArchivedSchedules();
    } else {
      archivedSection.style.display = "none";
      activeSection.style.display = "block";
      toggleArchiveBtn.textContent = "View Archived";
      currentView = "active";
      loadActiveSchedules();
    }
  });

  // ----------------------- DATA LOADERS ------------------------

  let schedulesById = {}; // Global lookup table

  async function loadActiveSchedules() {
    showLoading(true);
    try {
      const res = await fetchJSON(BASE_URL + "get_active.php");
      schedules.active = Array.isArray(res.data) ? res.data : [];

      // Populate lookup table without wiping archived schedules
      schedules.active.forEach((s) => {
        schedulesById[s.id] = s; // Consistent with PHP 'id'
      });

      render("active");
    } catch (e) {
      console.error(e);
      showToast("Failed to load active schedules.", false);
    }
    showLoading(false);
  }

  async function loadArchivedSchedules() {
    showLoading(true);
    try {
      const res = await fetchJSON(BASE_URL + "get_archived.php");
      schedules.archived = Array.isArray(res.data) ? res.data : [];

      // Add archived schedules to the same lookup table
      schedules.archived.forEach((s) => {
        schedulesById[s.id] = s;
      });

      render("archived");
    } catch (e) {
      console.error(e);
      showToast("Failed to load archived schedules.", false);
    }
    showLoading(false);
  }

  // ----------------------- RENDER LOGIC ------------------------

  function render(view) {
    const list = view === "active" ? activeList : archivedList;
    const data = schedules[view];
    list.innerHTML = "";

    if (!data || !data.length) {
      list.innerHTML = `<p class="empty">No ${view} schedules found.</p>`;
      return;
    }

    const groups = {};
    data.forEach((s) => {
      if (!groups[s.day]) groups[s.day] = [];
      groups[s.day].push(s);
    });

    const order = [
      "Monday",
      "Tuesday",
      "Wednesday",
      "Thursday",
      "Friday",
      "Saturday",
      "Sunday",
    ];

    let firstDayRendered = false;

    order.forEach((day) => {
      if (!groups[day]) return;

      // 1. Create wrapper and header
      const wrapper = document.createElement("div");
      // Add 'collapsed' class by default, except for the first day
      const isFirst = !firstDayRendered;
      wrapper.className = `day-schedule ${isFirst ? "expanded" : "collapsed"}`;
      wrapper.dataset.day = day;

      wrapper.innerHTML = `
                <div class="day-header">
                    <h4>${day} <span class="toggle-icon">${
        isFirst ? "&#9660;" : "&#9658;"
      }</span></h4>
                    <p class="count">${groups[day].length} Classes Available</p>
                </div>
                <div class="class-list" style="display: ${
                  isFirst ? "block" : "none"
                };">
                    </div>
                `;

      // 2. Insert cards into the class-list
      const classList = wrapper.querySelector(".class-list");

      groups[day]
        .sort((a, b) => (a.start_time ?? "").localeCompare(b.start_time ?? ""))
        .forEach((s) => classList.appendChild(renderCard(s)));

      list.appendChild(wrapper);

      if (!firstDayRendered) {
        firstDayRendered = true;
      }
    });
  }

  // ----------------------- CARD TEMPLATE (MODIFIED) ------------------------

  function renderCard(s) {
    const div = document.createElement("div");
    const statusClass = (s.status ?? "").replace(/\s+/g, "-").toLowerCase();

    const studentCount = parseInt(s.student_count) || 0;
    const roomCapacity = parseInt(s.room_capacity) || null;

    let capacityClass = "";
    // Check capacity status for visual feedback
    if (roomCapacity !== null && studentCount >= roomCapacity) {
      capacityClass = "capacity-full"; // Full capacity reached 🔴
    } else if (roomCapacity !== null && studentCount >= roomCapacity * 0.8) {
      capacityClass = "capacity-warning"; // Getting close to capacity 🟡
    }

    div.className = `schedule-card ${
      statusClass ? "status-" + statusClass : ""
    } ${capacityClass}`;
    div.dataset.id = s.id;

    const imgFile = (s.instructor_image || "").split("/").pop();
    const img = imgFile
      ? `/SANROOM/public/assets/img/instructors/${imgFile}`
      : `/SANROOM/public/assets/img/instructors/default.png`;

    // Combine details into a clean structure
    const detailsContent = `
            <p>Email: <a href="mailto:${s.instructor_email ?? ""}">${
      s.instructor_email ?? ""
    }</a></p>
            <p>Phone: <a href="tel:${s.instructor_phone ?? ""}">${
      s.instructor_phone ?? ""
    }</a></p>
            <p>Course Code: ${s.course_code ?? ""}</p>
            <p>Join Code: ${s.join_code ?? ""}</p>
        `;

    // Display Capacity and Student Count
    const capacityText = `Capacity: ${studentCount} / ${
      roomCapacity !== null ? roomCapacity : "N/A"
    }`;

    const timeAndRoom = `${s.start_time ?? ""} - ${s.end_time ?? ""} | ${
      s.room_name ?? "No Room"
    }`;

    div.innerHTML = `
            <div class="schedule-info">
                <img src="${img}" class="instructor-img" onerror="this.src='/SANROOM/public/assets/img/instructors/default.png'">
                <div class="instructor-details">
                    <strong>${s.class_name ?? ""}</strong>
                    <p>Instructor: ${s.instructor_name ?? ""}</p>
                    <p class="details-text">${timeAndRoom}</p>
                    <p class="capacity-text"><strong>${capacityText}</strong></p>
                    <div class="extra-details">
                        ${detailsContent}
                    </div>
                </div>
            </div>
            <div class="schedule-actions">
                <span class="status-badge ${statusClass}">${capitalize(
      s.status ?? ""
    )}</span>
                <div class="action-group">
                    <button class="btn-students" data-id="${
                      s.id
                    }">Students (${studentCount})</button>
                    ${
                      s.status === "archived"
                        ? `<button class="btn-restore" data-id="${s.id}">Restore</button>`
                        : `
                        <button class="btn-edit" data-id="${s.id}">Edit</button>
                        <button class="btn-archive" data-id="${s.id}">Archive</button>
                        <button class="btn-online" data-id="${s.id}">Online</button>
                        <button class="btn-suspend" data-id="${s.id}">Suspend</button>
                        <button class="btn-face" data-id="${s.id}">Face-to-Face</button>
                        `
                    }
                </div>
            </div>
        `;
    return div;
  }

  // ----------------------- ACCORDION TOGGLE HANDLER ------------------------

  document.addEventListener("click", (e) => {
    const header = e.target.closest(".day-schedule .day-header");
    if (header) {
      const wrapper = header.closest(".day-schedule");
      const classList = wrapper.querySelector(".class-list");
      const icon = wrapper.querySelector(".toggle-icon");

      if (wrapper.classList.contains("collapsed")) {
        // Expand
        classList.style.display = "block";
        wrapper.classList.remove("collapsed");
        wrapper.classList.add("expanded");
        if (icon) icon.innerHTML = "&#9660;"; // Down Arrow
      } else {
        // Collapse
        classList.style.display = "none";
        wrapper.classList.remove("expanded");
        wrapper.classList.add("collapsed");
        if (icon) icon.innerHTML = "&#9658;"; // Right Arrow
      }
    }
  });

  // ----------------------- ACTION BUTTONS ------------------------

  document.addEventListener("click", async (e) => {
    const btn = e.target.closest("button");
    if (!btn || !btn.dataset.id) return;

    const id = btn.dataset.id;
    if (btn.classList.contains("btn-edit")) await editSchedule(id);
    else if (btn.classList.contains("btn-archive"))
      await updateStatus(id, "archived");
    else if (btn.classList.contains("btn-suspend"))
      await updateStatus(id, "suspended");
    else if (btn.classList.contains("btn-online"))
      await updateStatus(id, "online");
    else if (btn.classList.contains("btn-face"))
      await updateStatus(id, "face-to-face");
    else if (btn.classList.contains("btn-restore"))
      await updateStatus(id, "active");
    // Student button handler
    else if (btn.classList.contains("btn-students")) await showStudents(id);
  });

  async function editSchedule(id) {
    showLoading(true);
    try {
      let schedule = schedulesById[id];

      // If not in memory, fetch from server
      if (!schedule) {
        const res = await fetchJSON(
          BASE_URL + `get_schedule.php?id=${encodeURIComponent(id)}`
        );
        if (!res.data) throw new Error("Schedule not found");
        schedule = res.data;

        // Add to lookup table for future
        schedulesById[schedule.id] = schedule;
      }

      // Populate the modal form
      populateForm(schedule);

      toggleModal(true);

      // Fetch students for the selected schedule's room
      if (schedule.room) {
        fetchStudentsForRoom(schedule.room);
      }
    } catch (e) {
      console.error(e);
      showToast("Error loading schedule.", false);
    } finally {
      showLoading(false);
    }
  }

  async function updateStatus(id, status) {
    showLoading(true);
    try {
      const res = await fetchJSON(BASE_URL + "update_status.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id, status }),
      });
      showToast(res.message, res.success);
      if (res.success) {
        // Reload active or archived lists to reflect the status change
        if (currentView === "active") await loadActiveSchedules();
        else await loadArchivedSchedules();
        try {
          localStorage.setItem("schedulesUpdated", Date.now().toString());
        } catch (e) {}
      }
    } catch (e) {
      console.error("Update Status Error:", e);
      showToast("Error updating schedule.", false);
    } finally {
      showLoading(false);
    }
  }

  /**
   * Converts a 12-hour time string (e.g., "02:30 PM", "2:30pm") to 24-hour format (e.g., "14:30").
   * This is CRITICAL because the PHP returns 12h time, but input type="time" requires 24h.
   * @param {string} time12h
   * @returns {string} Time in HH:MM format (24-hour) or empty string if invalid.
   */
  function convertTo24Hour(time12h) {
    if (!time12h) return "";

    // Regex to capture Hour, Minute, and optional AM/PM part robustly
    const parts = time12h.match(/(\d{1,2}):(\d{2})\s*(AM|PM|am|pm)?/i);

    if (!parts) {
      // Check if it's already a valid 24h string (HH:MM) without AM/PM
      if (time12h.includes(":") && time12h.length === 5) return time12h;
      return "";
    }

    let hour = parseInt(parts[1], 10);
    const minute = parts[2];
    const period = parts[3]?.toUpperCase();

    // If AM/PM is present, perform 12h conversion
    if (period) {
      if (period === "PM" && hour < 12) {
        hour += 12;
      } else if (period === "AM" && hour === 12) {
        hour = 0; // 12:xx AM is 00:xx in 24h
      }
    }

    // Return time in HH:MM format required by input type="time"
    return `${String(hour).padStart(2, "0")}:${minute}`;
  }

  function populateForm(data) {
    $("modalTitle").textContent = "Edit Schedule";
    if (inputs.id) inputs.id.value = data.id ?? "";
    if (inputs.className) inputs.className.value = data.class_name ?? "";
    if (inputs.instructor) inputs.instructor.value = data.instructor_name ?? "";
    if (inputs.instructorEmail)
      inputs.instructorEmail.value = data.instructor_email ?? "";
    if (inputs.instructorPhone)
      inputs.instructorPhone.value = data.instructor_phone ?? "";
    if (inputs.courseCode) inputs.courseCode.value = data.course_code ?? "";
    if (inputs.joinCode) inputs.joinCode.value = data.join_code ?? "";
    if (inputs.room) inputs.room.value = data.room ?? "";
    if (inputs.roomCapacity)
      inputs.roomCapacity.value = data.room_capacity ?? "";
    if (inputs.day) inputs.day.value = data.day ?? "";

    if (inputs.startTime)
      inputs.startTime.value = convertTo24Hour(data.start_time);
    if (inputs.endTime) inputs.endTime.value = convertTo24Hour(data.end_time);
  }

  // ----------------------- STUDENT / CAPACITY FUNCTIONALITY ------------------------

  /**
   * Fetches and displays the list of students for a given room.
   * This simulates the data reflection when a student joins.
   * @param {string} roomId
   */
  async function fetchStudentsForRoom(roomId) {
    if (!studentListContainer) return;

    studentListContainer.innerHTML = "Loading students...";

    try {
      // NOTE: This endpoint must return the student list for the given room
      const res = await fetchJSON(
        BASE_URL + `get_room_students.php?room_id=${encodeURIComponent(roomId)}`
      );

      if (res.success && Array.isArray(res.data) && res.data.length > 0) {
        let html = '<h5>Enrolled Students:</h5><ul class="student-list">';
        res.data.forEach((student) => {
          // Assuming student object has properties like 'name' and 'id'
          html += `<li>${student.name} (${student.id})</li>`;
        });
        html += "</ul>";
        studentListContainer.innerHTML = html;
      } else {
        studentListContainer.innerHTML = `<p>No students enrolled in room <strong>${roomId}</strong> yet.</p>`;
      }
    } catch (e) {
      console.error("Error fetching students:", e);
      studentListContainer.innerHTML = `<p class="error">Failed to load student list for room ${roomId}.</p>`;
    }
  }

  /**
   * Finds the schedule and calls the function to display students (placeholder).
   * @param {string} id - Schedule ID
   */
  async function showStudents(id) {
    const schedule =
      schedules.active.find((s) => s.id === id) ||
      schedules.archived.find((s) => s.id === id);

    if (!schedule || !schedule.room) {
      showToast("Schedule or Room data missing.", false);
      return;
    }

    // This is a placeholder for a real student list modal
    showToast(
      `Showing student list for room: ${schedule.room} (Current Students: ${schedule.student_count})`,
      true
    );
  }

  // ----------------------- HELPERS ------------------------

  function capitalize(str) {
    return str ? str.replace(/\b\w/g, (c) => c.toUpperCase()) : "";
  }
  async function fetchJSON(url, options = {}) {
    const res = await fetch(url, options);
    // Added a check to log the raw text if JSON parsing fails, aiding debugging
    if (!res.ok) {
      const text = await res.text();
      console.error(`HTTP Error ${res.status}: ${text}`);
      throw new Error(`HTTP Error ${res.status}`);
    }
    return await res.json();
  }
  function showToast(msg, ok = true) {
    const box = $("toastContainer");
    if (!box) return;
    const t = document.createElement("div");
    t.className = "toast " + (ok ? "success" : "error");
    t.textContent = msg;
    box.appendChild(t);
    setTimeout(() => t.remove(), 3000);
  }
  function showLoading(state) {
    document
      .querySelectorAll("button")
      .forEach((btn) => (btn.disabled = state));
  }

  // ----------------------- INITIAL LOAD ------------------------
  if (modal) {
    toggleModal(false);
  }
  loadActiveSchedules();
});
