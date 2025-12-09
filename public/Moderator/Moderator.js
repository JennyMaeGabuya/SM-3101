/**
 * Moderator.js
 * Handles basic client-side logic for login and dashboard UX.
 */

document.addEventListener("DOMContentLoaded", function () {
  const loginForm = document.getElementById("loginForm");

  if (loginForm) {
    const signInButton = loginForm.querySelector(".btn");
    const usernameInput = document.getElementById("username");
    const passwordInput = document.getElementById("password");

    // --- 1. Login UX: Disable Button on Submit ---
    loginForm.addEventListener("submit", function (event) {
      // Basic input validation check
      if (
        usernameInput.value.trim() === "" ||
        passwordInput.value.trim() === ""
      ) {
        return;
      }

      // Disable Button and Show Loading State
      signInButton.textContent = "Signing In...";
      signInButton.disabled = true;
      // The form submission continues here, relying on PHP for the redirect.
    });

    // --- 2. Reset Button State on Input Change (Good UX) ---
    function resetButtonState() {
      if (signInButton.disabled) {
        signInButton.textContent = "Sign In";
        signInButton.disabled = false;
      }
    }

    usernameInput.addEventListener("focus", resetButtonState);
    passwordInput.addEventListener("focus", resetButtonState);
  }

  // The showToast function definition should ideally be available globally or loaded separately,
  // but placing it here ensures it's available for the dashboard page when the JS loads.
});

/**
 * Global function to display the toast notification.
 * This is called by the PHP code on the Dashboard page.
 * @param {string} message The message to display.
 * @param {string} type The type of toast (e.g., 'success', 'error', 'info').
 */
function showToast(message, type = "info") {
  const container = document.getElementById("toast-container");
  if (!container) return;

  // Create the toast element
  const toast = document.createElement("div");
  toast.className = `toast ${type}`;
  toast.textContent = message;

  // Append and display
  container.appendChild(toast);

  // Show animation (using 'show' class)
  setTimeout(() => {
    toast.classList.add("show");
  }, 100);

  // Hide and remove after 4 seconds
  setTimeout(() => {
    toast.classList.remove("show");
    // Wait for fade-out animation before removing from DOM
    toast.addEventListener("transitionend", () => {
      container.removeChild(toast);
    });
  }, 4000);
}
