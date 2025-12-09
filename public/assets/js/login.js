/* ============================================================
   FINAL FIXED LOGIN.JS
   Supports:
   ✔ URL QR -> auto-fetch email from database
   ✔ Labeled QR -> Email, Code, Token directly
   ✔ Auto-fills email + activation code for first-time login
   ✔ Activated users must type real password
   ============================================================ */

let toastTimeout;
const loginForm = document.getElementById("loginForm");

// Fields
const usernameField = document.getElementById("username");
const passwordField = document.getElementById("password");
const accessCodeField = document.getElementById("access_code");

// Toast
const toast = document.createElement("div");
toast.className = "toast";
document.body.appendChild(toast);

// QR
const qrLoginBtn = document.getElementById("qrLoginBtn");
const qrFileInput = document.getElementById("qrFileInput");

// Stores activation code from QR (for first-time users)
let activationCodeFromQR = "";

/* ============================================================
   QR PARSER
============================================================ */
function parseQrData(qrRaw) {
  qrRaw = qrRaw?.trim() || "";
  console.log("🔴 RAW QR:", qrRaw);

  // ------------------ URL QR ------------------
  if (/^https?:\/\//i.test(qrRaw)) {
    const url = new URL(qrRaw);
    const token = url.searchParams.get("token") || "";

    if (!token) throw new Error("Missing token in URL QR");

    return { type: "url", email: "", token, code: token };
  }

  // ---------------- Labeled QR ----------------
  if (
    qrRaw.includes("Email:") ||
    qrRaw.includes("Code:") ||
    qrRaw.includes("Token:")
  ) {
    const parts = qrRaw.split("|");
    if (parts.length < 3)
      throw new Error("Labeled QR must contain Email, Code, and Token");

    let email = "",
      code = "",
      token = "";

    parts.forEach((p) => {
      const [label, value] = p.split(":");
      if (!label || !value) return;

      switch (label.trim().toLowerCase()) {
        case "email":
          email = value.trim();
          break;
        case "code":
          code = value.trim();
          break;
        case "token":
          token = value.trim();
          break;
      }
    });

    if (!email || !code || !token)
      throw new Error("Missing Email, Code, or Token in QR.");

    return { type: "labeled", email, code, token };
  }

  throw new Error("Invalid QR format. Expected URL or Email:X|Code:Y|Token:Z");
}

/* ============================================================
   QR DECODER
============================================================ */
async function decodeQRCodeImage(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => {
      const img = new Image();
      img.onload = function () {
        try {
          const canvas = document.createElement("canvas");
          const ctx = canvas.getContext("2d");
          canvas.width = img.width;
          canvas.height = img.height;
          ctx.drawImage(img, 0, 0);

          const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
          const qrResult = jsQR(
            imageData.data,
            imageData.width,
            imageData.height
          );

          if (!qrResult) return resolve(null);

          resolve(parseQrData(qrResult.data));
        } catch (err) {
          reject(err);
        }
      };
      img.src = reader.result;
    };
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });
}

/* ============================================================
   TOAST
============================================================ */
function showToast(message, type = "info") {
  const colors = {
    success: "#4CAF50",
    error: "#E63946",
    info: "#457b9d",
    warning: "#FF6347",
  };

  toast.textContent = message;
  toast.style.backgroundColor = colors[type] || colors.info;
  toast.classList.remove("fade-out", "show");
  void toast.offsetWidth;
  toast.classList.add("show");

  clearTimeout(toastTimeout);
  toastTimeout = setTimeout(() => fadeOutToast(), 3000);
}

function fadeOutToast() {
  toast.classList.add("fade-out");
}

/* ============================================================
   FORM SUBMISSION
============================================================ */
function submitLogin(formData) {
  console.log("➡️ Sending login request...");

  fetch("backend/login_process.php", {
    method: "POST",
    headers: { "X-Requested-With": "XMLHttpRequest" },
    body: formData,
  })
    .then(handleResponse)
    .catch(handleFetchError);
}

if (loginForm) {
  loginForm.addEventListener("submit", (e) => {
    e.preventDefault();

    const username = usernameField.value.trim();
    const enteredPassword = passwordField.value.trim();
    const accessCode = accessCodeField.value.trim();

    const isQrLogin = !!activationCodeFromQR;

    let actualPassword = enteredPassword;

    // FIRST-TIME USER: password = activation code
    if (isQrLogin && !enteredPassword) {
      actualPassword = activationCodeFromQR;
      console.log("🟢 Using Activation Code as password (first-time)");
    }

    if (!username || !actualPassword || !accessCode)
      return showToast("All fields are required.", "error");

    const formData = new FormData();
    formData.append("username", username);
    formData.append("password", actualPassword);
    formData.append("access_code", accessCode);

    if (isQrLogin) {
      formData.append("qr_token", accessCode);
      formData.append("qr_email", username);
    }

    submitLogin(formData);
  });
}

/* ============================================================
   QR LOGIN LOGIC (FIXED)
============================================================ */
if (qrLoginBtn && qrFileInput) {
  qrLoginBtn.addEventListener("click", () => qrFileInput.click());

  qrFileInput.addEventListener("change", async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    showToast("Processing QR...", "info");

    try {
      const qrData = await decodeQRCodeImage(file);
      if (!qrData) return showToast("No QR code found.", "error");

      // ---------------------------- URL QR ----------------------------
      if (qrData.type === "url") {
        console.log("🌐 URL QR detected → fetching email...");

        const res = await fetch(`backend/lookup_qr.php?token=${qrData.token}`);
        const info = await res.json();

        if (!info.success) return showToast("Invalid QR Token.", "error");

        usernameField.value = info.email;

        // first-time login uses activation code = token
        activationCodeFromQR = qrData.code;
      }

      // -------------------------- Labeled QR --------------------------
      if (qrData.type === "labeled") {
        usernameField.value = qrData.email;
        activationCodeFromQR = qrData.code;
      }

      accessCodeField.value = qrData.token;

      passwordField.value = "";
      passwordField.placeholder =
        "Enter password (or activation code if first-time)";

      showToast("QR scanned. Enter password to continue.", "success");

      passwordField.focus();
    } catch (err) {
      showToast(err.message, "error");
    } finally {
      e.target.value = null;
    }
  });
}

/* ============================================================
   FETCH HANDLERS
============================================================ */
function handleResponse(res) {
  if (!res.ok) throw new Error("HTTP Error " + res.status);

  return res.text().then((text) => {
    try {
      const data = JSON.parse(text);
      showToast(data.message, data.success ? "success" : "error");

      if (data.success) {
        setTimeout(() => {
          window.location.href = data.redirect || "dashboard.php";
        }, 1500);
      }
    } catch (err) {
      showToast("Server JSON error", "error");
    }
  });
}

function handleFetchError(err) {
  console.error("❌ FETCH ERROR:", err);
  showToast("Login Failed: " + err.message, "error");
}
