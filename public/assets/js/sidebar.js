document.addEventListener("DOMContentLoaded", function () {
  // Get the current path from the URL, remove the leading slash for accurate comparison
  const currentPath = window.location.pathname.split("/").pop(); // Get the last part of the URL path

  // Get all anchor tags within the sidebar
  const sidebarLinks = document.querySelectorAll(".sidebar a");

  // Loop through the links and remove 'active' class from all links
  sidebarLinks.forEach((link) => {
    link.classList.remove("active"); // Remove active class from all links
    // Compare the file name part of the URL with the href value
    if (link.getAttribute("href").split("/").pop() === currentPath) {
      link.classList.add("active"); // Add active class to the matching link
    }
  });
});
