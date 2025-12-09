// Update current time every second
function updateTime() {
  const now = new Date();
  document.getElementById("currentTime").textContent = now.toLocaleTimeString();
}
setInterval(updateTime, 1000);
updateTime();

// Function to handle logout
document.querySelector(".btn-logout").addEventListener("click", function () {
  // Clear the user data (username) from localStorage
  localStorage.removeItem("username");

  // Optionally, clear any other session-related data
  // sessionStorage.clear(); // Uncomment if you want to clear sessionStorage as well.

  // Redirect to the index page (login page)
  window.location.href = "index.php"; // Replace with your login page URL
});

// Optionally, display the current username if it's stored in localStorage
const savedUsername = localStorage.getItem("username");
if (savedUsername) {
  document.getElementById("usernameDisplay").textContent = savedUsername;
}

function updateTime() {
  const now = new Date();
  document.getElementById("currentTime").textContent = now.toLocaleTimeString();
}
setInterval(updateTime, 1000);
updateTime();
