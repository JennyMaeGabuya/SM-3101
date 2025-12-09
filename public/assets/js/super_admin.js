// super_admin.js

// Set the maximum number of toasts allowed to be visible
const MAX_VISIBLE_TOASTS = 2;
const TOAST_DURATION_MS = 5000;
// NOTE: Replace 1 with the actual Super Admin ID from your PHP session
const SUPER_ADMIN_ID = 1;
// NOTE: Must match the hardcoded token in fetch_teachers.php for auth
const AUTH_TOKEN = "Bearer super_admin_id_123";

// Function to generate a random alphanumeric string (for code/password)
function generateRandomCode(prefix = "", length = 10) {
  // Stronger character set for password generation
  const chars =
    "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz01234456789!@#$%^&*";
  let code = "";
  for (let i = 0; i < length; i++) {
    code += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return prefix + code;
}

// --- DOM Elements ---
const menuToggle = document.getElementById("menuToggle");
const sidebar = document.getElementById("sidebar");
const createTeacherForm = document.getElementById("createTeacherForm");
const submitButton = createTeacherForm.querySelector('button[type="submit"]');

// Password elements
const initialPasswordInput = document.getElementById("initialPassword");
const generatePasswordBtn = document.getElementById("generatePasswordBtn");

// Activation Code elements
const generateActivationCodeBtn = document.getElementById(
  "generateActivationCodeBtn"
);
const activationCodeInput = document.getElementById("activationCode");

// QR Modal elements
const qrModal = document.getElementById("qrModal");
const qrCodeContainer = document.getElementById("qrCodeContainer"); // Container DIV for qrcode.js
const qrTeacherName = document.getElementById("qrTeacherName");
const qrTokenDisplay = document.getElementById("qrTokenDisplay");
const downloadQrBtn = document.getElementById("downloadQrBtn");
// NOTE: closeQrModalBtn was missing in HTML but is referenced here, keeping reference for completeness
const closeQrModalBtn = document.getElementById("closeQrModalBtn");

// Accordion elements (These appear to be related to a Logs section not shown in the HTML)
// const accordionHeader = document.getElementById("accordionHeader");
// const accordionContent = document.getElementById("accordionContent");
const teacherListTableBody = document.getElementById("teacherListTableBody"); // Corrected target for teacher list
const emptyTableMessage = document.getElementById("emptyTableMessage");

// --- Initialization ---
initialPasswordInput.readOnly = true;

window.onload = function () {
  // Generate activation code on load
  const newCode = generateRandomCode("AC-", 8);
  activationCodeInput.value = newCode;

  // Assuming accordion logic is now handled in HTML or not needed for this table view
  // accordionContent.classList.add("open");
  // accordionHeader.classList.add("active");

  initialPasswordInput.value = "Welcome123";

  fetchTeacherLogs();
};

// --- API Fetching Logic ---

/**
 * Fetches the list of all teachers and their *initial* creation log data.
 */
async function fetchTeacherLogs() {
  try {
    // 🎯 Corrected path (assuming fetch_logs.php is the teacher list endpoint)
    const response = await fetch("backends/fetch_logs.php", {
      method: "GET",
      headers: {
        Authorization: AUTH_TOKEN, // Pass the required token for authentication
        "Content-Type": "application/json",
      },
    });

    if (!response.ok) {
      const errorText = await response.text();
      let errorMessage = `HTTP Error ${response.status}: ${response.statusText}.`;

      try {
        const errorResult = JSON.parse(errorText);
        errorMessage = errorResult.message || errorMessage;
      } catch (e) {
        // Handle non-JSON response (e.g., HTML 404 page)
      }

      throw new Error(errorMessage);
    }

    const result = await response.json();

    if (result.status === "success") {
      showToast(
        "Data Fetch Success",
        `${result.count} teacher accounts loaded.`,
        "success"
      );
      renderTeacherLogs(result.teachers);
    } else {
      // Handle errors from PHP (e.g., database error)
      showToast("Data Fetch Failed", result.message, "danger", 10000);
    }
  } catch (error) {
    console.error("Fetch Error:", error);
    showToast(
      "Server Error",
      error.message || "Could not fetch teacher data from the server.",
      "danger",
      10000
    );
  }
}

// --- Teacher Rendering and Action Logic ---

/**
 * Renders the fetched teacher data into the current teachers table.
 * @param {Array<Object>} teachers - Array of teacher objects including log data.
 */
function renderTeacherLogs(teachers) {
  const tableBody = teacherListTableBody;
  tableBody.innerHTML = ""; // Clear existing content
  emptyTableMessage.classList.add("hidden"); // Assume data will be rendered

  if (teachers.length === 0) {
    emptyTableMessage.classList.remove("hidden");
    return;
  }

  teachers.forEach((teacher) => {
    // --- Status Logic ---
    let isActiveStatus;
    let statusClass;

    if (teacher.is_active === "1" || teacher.is_active === 1) {
      isActiveStatus = "Active";
      statusClass = "bg-status-online/20 text-status-online";
    } else if (teacher.is_active === "0" || teacher.is_active === 0) {
      isActiveStatus = "Pending";
      // NOTE: Using status-warning for pending setup
      statusClass = "bg-status-warning/20 text-status-warning";
    } else if (teacher.is_active === "-1" || teacher.is_active === -1) {
      isActiveStatus = "Deleted";
      statusClass = "bg-status-danger/20 text-status-danger";
    } else {
      isActiveStatus = "Unknown";
      statusClass = "bg-gray-200/50 text-text-subtle";
    }

    // --- Date Formatting ---
    const createdAtDate = new Date(teacher.created_at);
    const createdTimeString =
      createdAtDate.toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric",
      }) +
      " " +
      createdAtDate.toLocaleTimeString("en-US", {
        hour: "2-digit",
        minute: "2-digit",
        hour12: true,
      });

    const newRow = document.createElement("tr");
    newRow.className = "hover:bg-gray-50 transition duration-150";

    // --- Table Row HTML Generation (MUST match the <thead> columns) ---
    newRow.innerHTML = `
        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-text-dark">${
          teacher.teacher_id
        }</td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-text-dark">${
          teacher.full_name
        }</td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-text-subtle">${
          teacher.email
        }</td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-text-dark">${
          teacher.department
        }</td>
        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-primary">${
          teacher.activation_code
        }</td>
        <td class="px-6 py-4 whitespace-nowrap">
            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">${isActiveStatus}</span>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-text-subtle">${createdTimeString}</td>
        <td class="px-6 py-4 text-xs text-text-subtle font-mono truncate max-w-xs">${
          teacher.qr_token ? teacher.qr_token.substring(0, 20) + "..." : "N/A"
        }</td>
        
        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
            <div class="flex items-center space-x-3 justify-center">
                <button 
                    data-teacher-id="${teacher.teacher_id}"
                    class="text-primary hover:text-blue-700 transition duration-150 edit-btn"
                    title="Edit Teacher Details"
                    ${teacher.is_active === "-1" ? "disabled" : ""}>
                    Edit
                </button>
                <button 
                    data-copy-code="${teacher.activation_code}"
                    class="text-secondary hover:text-text-dark transition duration-150 copy-btn"
                    title="Copy Activation Code">
                    Copy
                </button>
                <button 
                    data-teacher-id="${teacher.teacher_id}"
                    data-teacher-name="${teacher.full_name}"
                    class="text-status-danger hover:text-red-700 transition duration-150 delete-btn"
                    title="Soft Delete Account"
                    ${teacher.is_active === "-1" ? "disabled" : ""}>
                    Delete
                </button>
                <button
                    data-teacher-name="${teacher.full_name}"
                    data-qr-token="${teacher.qr_token}"
                    class="text-status-online hover:text-green-700 transition duration-150 qr-btn"
                    title="Show QR Code"
                    ${teacher.qr_token ? "" : "disabled"}>
                    QR
                </button>
            </div>
        </td>
    `;
    // NOTE: Removed `password_hash` column from rendering as it is sensitive raw data.

    tableBody.appendChild(newRow);
  });

  // Attach listeners after all rows are rendered
  attachActionListeners();
}

/**
 * Attaches event listeners to the newly rendered Edit, Copy, and Delete buttons.
 */
function attachActionListeners() {
  // 1. Edit Button Logic (Placeholder)
  document.querySelectorAll(".edit-btn:not([disabled])").forEach((button) => {
    button.addEventListener("click", function () {
      const teacherId = this.getAttribute("data-teacher-id");
      showToast(
        "Action Pending",
        `Edit button clicked for ID: ${teacherId}. Need to implement edit modal/logic.`,
        "info"
      );
      // NOTE: We will implement this next!
    });
  });

  // 2. Copy Button Logic
  document.querySelectorAll(".copy-btn").forEach((button) => {
    button.addEventListener("click", function () {
      const codeToCopy = this.getAttribute("data-copy-code");
      navigator.clipboard
        .writeText(codeToCopy)
        .then(() => {
          showToast(
            "Copied!",
            `Activation Code copied to clipboard.`,
            "success",
            3000
          );
        })
        .catch((err) => {
          console.error("Copy failed", err);
          showToast("Copy Failed", "Please copy manually.", "danger", 3000);
        });
    });
  });

  // 3. QR Button Logic (New)
  document.querySelectorAll(".qr-btn:not([disabled])").forEach((button) => {
    button.addEventListener("click", function () {
      const teacherName = this.getAttribute("data-teacher-name");
      const qrToken = this.getAttribute("data-qr-token");
      if (qrToken) {
        displayQrModal(teacherName, qrToken);
      } else {
        showToast("Error", "QR token is missing for this account.", "danger");
      }
    });
  });

  // 4. Delete Button Logic (Soft Delete)
  document.querySelectorAll(".delete-btn:not([disabled])").forEach((button) => {
    button.addEventListener("click", function () {
      const teacherId = this.getAttribute("data-teacher-id");
      const teacherName = this.getAttribute("data-teacher-name");

      if (
        confirm(
          `Are you sure you want to soft delete the account for ${teacherName} (ID: ${teacherId})? This action can be reversed by editing the account.`
        )
      ) {
        deleteTeacher(teacherId, teacherName);
      }
    });
  });
}

/**
 * Handles the soft deletion of a teacher account by setting is_active to -1.
 * You will need to create the corresponding PHP endpoint: backends/delete_teacher.php
 */
async function deleteTeacher(teacherId, teacherName) {
  showToast(
    "Deleting...",
    `Attempting to soft delete ${teacherName}...`,
    "info"
  );

  try {
    const response = await fetch("backends/delete_teacher.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: AUTH_TOKEN,
      },
      body: JSON.stringify({
        teacher_id: teacherId,
        super_admin_id: SUPER_ADMIN_ID,
      }),
    });

    if (!response.ok) {
      const errorText = await response.text();
      let errorMessage = `HTTP Error ${response.status}: ${response.statusText}.`;
      try {
        const errorResult = JSON.parse(errorText);
        errorMessage = errorResult.message || errorMessage;
      } catch (e) {
        /* ignore */
      }
      throw new Error(errorMessage);
    }

    const result = await response.json();

    if (result.status === "success") {
      showToast(
        "Deleted!",
        `${teacherName} has been soft-deleted. Status will update.`,
        "success"
      );
      // Re-fetch data to update the table with the 'Deleted' status
      fetchTeacherLogs();
    } else {
      showToast("Delete Failed", result.message, "danger");
    }
  } catch (error) {
    console.error("Delete Error:", error);
    showToast(
      "Network Error",
      error.message || "Could not connect to the server to delete.",
      "danger"
    );
  }
}

// --- Event Listeners (Form and UI) ---

// Sidebar Toggle Logic (Mobile)
menuToggle.addEventListener("click", () => {
  sidebar.classList.toggle("open");
});

// Generate Initial Password Logic (Manual Override)
generatePasswordBtn.addEventListener("click", () => {
  const newPassword = generateRandomCode("", 12);
  initialPasswordInput.value = newPassword;
  initialPasswordInput.classList.remove("bg-gray-50", "text-text-subtle");
  initialPasswordInput.classList.add("bg-white", "text-text-dark", "font-mono");
  showToast(
    "Password Generated",
    `Initial password updated to: ${newPassword}`,
    "success"
  );
});

// Generate Activation Code Logic
generateActivationCodeBtn.addEventListener("click", () => {
  const newCode = generateRandomCode("AC-", 8);
  activationCodeInput.value = newCode;
  showToast("Code Generated", `New activation code: ${newCode}`, "success");
});

// NOTE: Removing unused Accordion Logic listeners
/*
accordionHeader.addEventListener("click", () => {
  accordionContent.classList.toggle("open");
  accordionHeader.classList.toggle("active");
});
*/

// QR Modal Close Logic
closeQrModalBtn.addEventListener("click", () => {
  qrModal.classList.remove("flex");
  qrModal.classList.add("hidden");
});

// Smooth Scroll Logic for Sidebar Link
document
  .querySelectorAll('a.sidebar-scroll-link[href^="#"]')
  .forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      e.preventDefault();
      document.querySelector(this.getAttribute("href")).scrollIntoView({
        behavior: "smooth",
      });
      if (window.innerWidth < 768) {
        sidebar.classList.remove("open");
      }
    });
  });

// --- Form Submission Logic (AJAX/Fetch) ---
createTeacherForm.addEventListener("submit", async function (e) {
  e.preventDefault();
  submitButton.disabled = true;
  submitButton.innerHTML = `<svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Creating...`; // 💡 FIX START: Create a FormData object instead of a raw JS object

  // 💡 FIX: Changed the form submission from sending JSON to sending FormData.
  // This is required when the PHP backend expects data via the standard $_POST superglobal,
  // which is how forms typically submit data. Using FormData correctly handles the encoding
  // (Content-Type: multipart/form-data) that PHP expects.

  const formData = new FormData(createTeacherForm); // Automatically collects form fields by their `name` attributes.
  // If your form fields have IDs but not names, you must manually add them like below:
  // formData.append("full_name", document.getElementById("fullName").value);
  // formData.append("email", document.getElementById("email").value);
  // ...

  // Ensure mandatory fields that might not be in the HTML form itself (but are in the script) are added:
  formData.append("super_admin_id", SUPER_ADMIN_ID);

  // NOTE: If your HTML <input> elements have name attributes like 'email', 'full_name', 'initial_password',
  // 'department', and 'activation_code', the FormData(createTeacherForm) constructor will handle them.
  // I will assume the HTML is correctly structured to use the constructor, but keep the manual mapping
  // for non-standard or manually generated fields.

  // Manually add the script-generated or hardcoded values.
  formData.set("initial_password", initialPasswordInput.value);
  formData.set("activation_code", activationCodeInput.value);

  try {
    const response = await fetch("backends/create_teacher.php", {
      method: "POST",
      // 💡 FIX: DO NOT set Content-Type header when using FormData. The browser sets the
      // correct 'multipart/form-data' boundary header automatically.
      headers: {
        Authorization: AUTH_TOKEN, // Auth token is still necessary
      },
      body: formData, // <-- Pass the FormData object directly
    });

    if (!response.ok) {
      const errorText = await response.text();
      let errorMessage = `HTTP Error ${response.status}: ${response.statusText}.`;

      try {
        const errorResult = JSON.parse(errorText);
        errorMessage = errorResult.message || errorMessage;
      } catch (e) {
        /* ignore if not JSON */
      }

      throw new Error(errorMessage);
    }

    const result = await response.json();

    if (result.status === "success") {
      showToast(
        "Success!",
        `Account for ${result.full_name} created.`,
        "success"
      );

      if (result.qr_token) {
        displayQrModal(result.full_name, result.qr_token);
      } else {
        showToast(
          "Warning",
          "Account created but QR token was missing from server response.",
          "danger"
        );
      }

      if (result.qr_warning) {
        showToast("QR Server Warning", result.qr_warning, "danger", 10000);
      }

      // Re-fetch and re-render the list to include the new teacher
      fetchTeacherLogs();

      resetForm();
    } else {
      // Handle server-side validation or database errors
      showToast("Creation Failed", result.message, "danger");
    }
  } catch (error) {
    console.error("Submission Error:", error);
    showToast(
      "Network Error",
      error.message || "Could not connect to the server.",
      "danger"
    );
  } finally {
    submitButton.disabled = false;
    submitButton.innerHTML = `<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 01-18 0 9 9 0118 0z"></path></svg> Create Account`;
  }
});

// Helper function to reset the form fields
function resetForm() {
  // Assuming form fields have IDs: fullName, email, department
  document.getElementById("fullName").value = "";
  document.getElementById("email").value = "";

  const departmentSelect = document.getElementById("department");
  if (departmentSelect) {
    departmentSelect.selectedIndex = 0;
  }

  initialPasswordInput.value = "Welcome123";
  initialPasswordInput.classList.remove(
    "bg-white",
    "text-text-dark",
    "font-mono"
  );
  initialPasswordInput.classList.add("bg-gray-50", "text-text-subtle");

  const newCode = generateRandomCode("AC-", 8);
  activationCodeInput.value = newCode;
}

// Helper function to display the QR modal (UPDATED FOR CLIENT-SIDE QR GENERATION)
function displayQrModal(name, token) {
  // 1. Clear previous QR Code
  qrCodeContainer.innerHTML = "";

  qrTeacherName.textContent = name;
  qrTokenDisplay.textContent = token;

  // 2. Define the data to be encoded in the QR Code
  // NOTE: Ensure this URL matches your actual deployment environment!
  const loginUrl = `https://yourdomain.com/login_qr.php?token=${token}`;

  // 3. Generate the QR Code using the qrcode.js library
  if (typeof QRCode !== "undefined") {
    new QRCode(qrCodeContainer, {
      text: loginUrl,
      width: 180,
      height: 180,
      colorDark: "#3b82f6", // Updated to match primary color from your HTML/CSS
      colorLight: "#ffffff",
      correctLevel: QRCode.CorrectLevel.H,
    });

    // 4. Set the download link properties (requires a slight delay)
    setTimeout(() => {
      const qrCanvas = qrCodeContainer.querySelector("canvas");
      if (qrCanvas) {
        // Convert canvas to image data URL
        const dataURL = qrCanvas.toDataURL("image/png");
        downloadQrBtn.href = dataURL;
        downloadQrBtn.download = `sanroom_qr_${name
          .toLowerCase()
          .replace(/\s/g, "_")}.png`;
      }
    }, 100);
  } else {
    // Fallback/Warning if qrcode.js isn't loaded
    qrCodeContainer.textContent =
      "QR Code generator failed to load. Check your HTML script tag.";
    showToast(
      "QR Error",
      "The qrcode.js library is missing or failed to load.",
      "danger"
    );
  }

  // 5. Show the modal
  qrModal.classList.remove("hidden");
  qrModal.classList.add("flex");
}

// --- Toast Notification Logic ---
function showToast(
  title,
  message,
  type = "info",
  duration = TOAST_DURATION_MS
) {
  const container = document.getElementById("toastContainer");

  // Ensure the toast container exists, if not, create it (assuming it was missing in the HTML)
  if (!container) {
    console.error(
      "Toast container missing. Add <div id='toastContainer'>...</div> to your HTML."
    );
    return;
  }

  if (container.children.length >= MAX_VISIBLE_TOASTS) {
    container.removeChild(container.children[0]);
  }

  const toast = document.createElement("div");
  toast.className = `toast ${type} my-2 rounded-lg shadow-xl p-4 animate-toastIn`;
  toast.setAttribute("role", "alert");
  toast.style.animationName = "toastIn";

  let iconHtml = "";
  if (type === "success") {
    iconHtml =
      '<svg class="w-6 h-6 mr-3 text-status-online" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 01-18 0 9 9 0118 0z"></path></svg>';
  } else if (type === "danger") {
    iconHtml =
      '<svg class="w-6 h-6 mr-3 text-status-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 01-18 0 9 9 0118 0z"></path></svg>';
  } else if (type === "info") {
    // Adjusted icon for info
    iconHtml =
      '<svg class="w-6 h-6 mr-3 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 01-18 0 9 9 0118 0z"></path></svg>';
  }

  toast.innerHTML = `
                <div class="flex items-start">
                    ${iconHtml}
                    <div class="flex-1">
                        <p class="font-bold">${title}</p>
                        <p class="text-sm">${message}</p>
                    </div>
                    <button type="button" class="ml-4 -mr-1.5 -mt-1.5 p-1.5 rounded-full inline-flex text-gray-500 hover:text-gray-900 transition duration-150" onclick="this.closest('.toast').remove()">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="progress-bar"></div>
            `;

  if (type === "danger") {
    toast.classList.remove("success");
    toast.style.backgroundColor = "#fef2f2";
    toast.style.color = "#991b1b";
    toast.style.border = "1px solid #fee2e2";
    toast.querySelector(".progress-bar").style.backgroundColor = "#ef4444";
  } else if (type === "success") {
    toast.style.backgroundColor = "#f0fdf4";
    toast.style.color = "#166534";
    toast.style.border = "1px solid #d1fae5";
    toast.querySelector(".progress-bar").style.backgroundColor = "#10b981";
  } else if (type === "info") {
    toast.style.backgroundColor = "#eff6ff";
    toast.style.color = "#1e40af";
    toast.style.border = "1px solid #bfdbfe";
    toast.querySelector(".progress-bar").style.backgroundColor = "#3b82f6";
  }

  container.appendChild(toast);

  setTimeout(() => {
    if (toast.parentNode) {
      toast.style.animationName = "toastOut";
      toast.addEventListener("animationend", () => {
        if (toast.parentNode) {
          toast.remove();
        }
      });
    }
  }, duration);
}

// NOTE: Retaining tailwind config for completeness, although it's usually defined separately.
tailwind.config = {
  theme: {
    extend: {
      fontFamily: {
        sans: ["Inter", "sans-serif"],
      },
      colors: {
        primary: "#3b82f6", // Adjusted to blue 500 for better Tailwind match
        secondary: "#1f2937", // Dark Slate Gray (for main text/sidebar background)
        "app-bg": "#f9fafb", // Very light gray for clean background
        "card-bg": "#ffffff", // Pure white for cards/surfaces
        "border-subtle": "#e5e7eb", // Light gray border
        "text-dark": "#111827", // Darkest text
        "text-subtle": "#6b7280", // Medium gray for descriptions
        "status-active": "#3b82f6", // Blue (Used for new/active)
        "status-online": "#10b981", // Emerald Green (softer than previous)
        "status-warning": "#f59e0b", // Amber
        "status-danger": "#ef4444", // Red
        "status-success": "#10b981", // Green for success messages
      },
      boxShadow: {
        card: "0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.02)",
        hover: "0 4px 6px rgba(0, 0, 0, 0.08)",
      },
      borderRadius: {
        lg: "0.75rem", // Slightly larger rounding for modern feel
      },
    },
  },
};
