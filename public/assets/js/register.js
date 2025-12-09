document
  .getElementById("registerForm")
  .addEventListener("submit", function (e) {
    e.preventDefault();

    let email = document.getElementById("email").value.trim();
    let password = document.getElementById("password").value.trim();
    let confirm = document.getElementById("confirm_password").value.trim();

    if (password !== confirm) {
      showToast("Passwords do not match!", "error");
      return;
    }

    let formData = new FormData();
    formData.append("email", email);
    formData.append("password", password);
    formData.append("confirm_password", confirm);

    fetch("backend/register_process.php", {
      method: "POST",
      body: formData,
    })
      .then((res) => res.json())
      .then((data) => {
        showToast(data.message, data.status);

        if (data.status === "success") {
          // Display the QR code
          const qrContainer = document.getElementById("qrContainer");
          qrContainer.innerHTML = `
            <h3 style="margin-bottom:10px;">Save this QR Code for future login:</h3>
            <img src="${data.qr_url}" alt="QR Code" style="width:200px; height:200px;">
          `;
          qrContainer.style.display = "block";
          qrContainer.scrollIntoView({ behavior: "smooth" });
        }
      })
      .catch((err) => {
        console.error("Fetch error:", err);
        showToast("Server error occurred.", "error");
      });
  });

// Toast function
function showToast(message, type = "info") {
  let toast = document.createElement("div");
  toast.className = "toast";

  if (type === "error") toast.style.background = "#e63946";
  if (type === "success") toast.style.background = "#2a9d8f";

  toast.textContent = message;
  document.body.appendChild(toast);

  setTimeout(() => toast.classList.add("show"), 100);

  setTimeout(() => {
    toast.classList.remove("show");
    setTimeout(() => toast.remove(), 500);
  }, 3000);
}
