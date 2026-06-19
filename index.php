<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Online Clearance Cloud - Login</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>" />
  </head>

  <body data-page="login">
    <!-- Notification Container -->
    <div id="notification" class="notification"></div>

    <div class="main-container">
      <div class="login-card">
        <div class="header">
          <h1>Welcome to Online Clearance Cloud</h1>
          <p>Academic Clearance Management System</p>
        </div>

        <!-- Role Tabs -->
        <div class="role-tabs">
          <button class="tab-btn active" onclick="selectRole(event, 'student')">
            Student
          </button>
          <button class="tab-btn" onclick="selectRole(event, 'admin')">
            Admin
          </button>
          <button class="tab-btn" onclick="selectRole(event, 'superadmin')">
            Super Admin
          </button>
        </div>

        <!-- Student Login -->
        <div
          id="student-login-panel"
          class="login-panel"
          style="display: block"
        >
          <form id="studentLoginForm" onsubmit="event.preventDefault(); handleStudentLogin();">
            <div class="form-group">
              <label for="studentIdInput">Student ID:</label>
              <input type="text" id="studentIdInput" placeholder="Enter student ID (e.g. STU001)" required>
            </div>
            <div class="form-group">
              <label for="studentPasswordInput">Password:</label>
              <input type="password" id="studentPasswordInput" placeholder="Enter password (default: student123)" required>
            </div>

            <button type="submit" class="btn btn-primary" id="studentLoginBtn">
              Login as Student
            </button>
          </form>
        </div>

        <!-- Admin Login -->
        <div id="admin-login-panel" class="login-panel" style="display: none">
          <form id="adminLoginForm" onsubmit="event.preventDefault(); loginAdmin();">
            <div class="form-group">
              <label for="adminUsername">Username:</label>
              <input type="text" id="adminUsername" placeholder="Enter admin username" required>
            </div>
            <div class="form-group">
              <label for="adminPassword">Password:</label>
              <input type="password" id="adminPassword" placeholder="Enter password" required>
            </div>
            <button type="submit" class="btn btn-primary">
              Login as Admin
            </button>
          </form>
        </div>

        <!-- Super Admin Login -->
        <div
          id="superadmin-login-panel"
          class="login-panel"
          style="display: none"
        >
          <form id="superAdminLoginForm" onsubmit="event.preventDefault(); loginSuperAdmin();">
            <div class="form-group">
              <label for="superAdminUsername">Username:</label>
              <input type="text" id="superAdminUsername" placeholder="Enter super admin username" required>
            </div>
            <div class="form-group">
              <label for="superAdminPassword">Password:</label>
              <input type="password" id="superAdminPassword" placeholder="Enter password" required>
            </div>
            <button type="submit" class="btn btn-primary">
              Login as Super Admin
            </button>
          </form>
        </div>

        <!-- Info Box -->
        <div class="info-box">
          <strong>ℹ️ Demo System Information:</strong>
          <p style="margin: 10px 0 0 0">
            Students must use their Student ID and password (e.g., <strong>STU001</strong> / <strong>student123</strong>).<br />
            Admins must use department-specific credentials (e.g., <strong>cse_admin</strong> / <strong>admin123</strong>).<br />
            Library Admin must use <strong>library_admin</strong> / <strong>admin123</strong>.<br />
            Super Admin must use <strong>super_admin</strong> / <strong>super123</strong>.
          </p>
        </div>

        <div class="footer-links">
          <p class="copyright">
            © 2025 Online Clearance Cloud. All rights reserved.
          </p>
        </div>
      </div>
    </div>

    <script src="assets/js/script.js?v=<?php echo time(); ?>"></script>
    <script>
      // Role selection
      function selectRole(event, role) {
        event.preventDefault();

        // Update active tab
        document
          .querySelectorAll(".tab-btn")
          .forEach((btn) => btn.classList.remove("active"));
        event.target.classList.add("active");

        // Show/hide panels
        document
          .querySelectorAll(".login-panel")
          .forEach((panel) => (panel.style.display = "none"));

        if (role === "student") {
          document.getElementById("student-login-panel").style.display =
            "block";
        } else if (role === "admin") {
          document.getElementById("admin-login-panel").style.display = "block";
        } else if (role === "superadmin") {
          document.getElementById("superadmin-login-panel").style.display =
            "block";
        }
      }
    </script>
  </body>
</html>
