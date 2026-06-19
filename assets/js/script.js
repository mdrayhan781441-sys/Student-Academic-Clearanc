// API Base URL - Automatically detect if we are in /pages/ directory
const API_BASE = window.location.pathname.includes('/pages/') ? '../api/' : 'api/';

// Store data for payment calculation
let currentDues = null;
let currentStudent = null;

// Utility function to make API calls
async function apiCall(endpoint, method = "GET", data = null) {
  try {
    const options = {
      method: method,
      headers: {
        "Content-Type": "application/json",
      },
    };

    if (data && method !== "GET") {
      options.body = JSON.stringify(data);
    }

    const url = API_BASE + endpoint;
    console.log(`Calling API: ${url}`, options);
    const response = await fetch(url, options);
    
    // Check if response is JSON
    const contentType = response.headers.get("content-type");
    if (!contentType || !contentType.includes("application/json")) {
      const text = await response.text();
      console.error("Non-JSON response from server:", text);
      throw new Error("Server returned non-JSON response. Check PHP logs.");
    }

    const result = await response.json();

    if (!response.ok || (result && result.success === false)) {
      throw new Error(result.message || "API Error");
    }

    return result;
  } catch (error) {
    console.error("API Error:", error);
    showAlert("Error: " + error.message, "danger");
    throw error;
  }
}

// Show alert message
function showAlert(message, type = "info") {
  // Scroll to top of page to ensure notification is visible
  window.scrollTo({
    top: 0,
    behavior: "smooth",
  });

  // Use notification container if available
  const notificationDiv = document.getElementById("notification");

  if (notificationDiv) {
    notificationDiv.textContent = message;
    notificationDiv.className = `notification show ${type}`;
    notificationDiv.style.display = "block";

    setTimeout(() => {
      notificationDiv.style.display = "none";
      notificationDiv.classList.remove("show");
    }, 4000);
  } else {
    // Fallback: create alert in main content
    const alertDiv = document.createElement("div");
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = message;

    const container = document.querySelector(".main-content") || document.body;
    container.insertBefore(alertDiv, container.firstChild);

    setTimeout(() => {
      alertDiv.remove();
    }, 4000);
  }
}

// Logout function
async function handleLogout() {
  if (confirm("Are you sure you want to logout?")) {
    const homePath = window.location.pathname.includes('/pages/') ? '../' : './';
    const apiPath = window.location.pathname.includes('/pages/') ? '../api/logout.php' : 'api/logout.php';
    try {
      fetch(apiPath, { method: "POST" }).catch(() => {});
      window.location.href = homePath;
    } catch (error) {
      window.location.href = homePath;
    }
  }
}

// === ANNOUNCEMENT TICKER GLOBAL LOAD ===
// Adjust ticker animation speed based on text width to ensure a consistent, readable scroll rate
function adjustTickerSpeed(tickerList) {
  if (!tickerList) return;
  setTimeout(() => {
    const scrollWidth = tickerList.scrollWidth;
    const distance = scrollWidth * 0.5;
    const speed = 45; // pixels per second
    const duration = distance > 0 ? (distance / speed) : 30;
    tickerList.style.setProperty('--ticker-duration', `${duration}s`);
  }, 100);
}

async function loadTickerAnnouncements() {
  const tickerList = document.getElementById("announcementTickerList");
  if (!tickerList) return;

  console.log("Fetching global ticker announcements...");
  try {
    const apiPath = (window.location.pathname.includes('/pages/') ? '../' : '') + "api/admin_announcements.php?t=" + new Date().getTime();
    const response = await fetch(apiPath);
    const data = await response.json();
    
    if (data.success && data.announcements && data.announcements.length > 0) {
      const tickerHtml = data.announcements.map(ann => `
        <span class="ticker-item"><strong>${ann.title}</strong>: ${ann.message}</span>
      `).join("");
      const repeatCount = data.announcements.length < 3 ? 4 : 2;
      tickerList.innerHTML = tickerHtml.repeat(repeatCount);
    } else {
      tickerList.innerHTML = `<span class="ticker-item">No announcements broadcasted yet.</span>`;
    }
    adjustTickerSpeed(tickerList);
  } catch (e) {
    console.error("Ticker Load Error:", e);
    tickerList.innerHTML = `<span class="ticker-item">No announcements broadcasted yet.</span>`;
    adjustTickerSpeed(tickerList);
  }
}

// === LOGIN PAGE FUNCTIONS ===
document.addEventListener("DOMContentLoaded", function () {
  // Initialize page based on current page
  const currentPage = document.querySelector("body").getAttribute("data-page");

  if (currentPage === "login") {
    initLoginPage();
  } else if (currentPage === "student_dashboard" || currentPage === "student-dashboard") {
    loadStudentDashboard();
  } else if (currentPage === "admin_dashboard" || currentPage === "admin-dashboard") {
    loadAdminDashboard();
  } else if (currentPage === "super_admin_dashboard" || currentPage === "super-admin-dashboard") {
    loadSuperAdminDashboard();
  }
  
  // Start notification polling for dashboards
  if (currentPage !== "login") {
      loadNotifications();
      loadTickerAnnouncements();
      setInterval(loadNotifications, 30000); // Every 30 seconds
  }
});



async function handleStudentLogin() {
  const studentIdInput = document.getElementById("studentIdInput");
  const studentPasswordInput = document.getElementById("studentPasswordInput");
  const studentId = studentIdInput ? studentIdInput.value.trim() : "";
  const password = studentPasswordInput ? studentPasswordInput.value : "";
  const btn = document.getElementById("studentLoginBtn");

  if (!studentId || !password) {
    showAlert("Please enter Student ID and Password", "warning");
    return;
  }

  if (btn) {
    btn.disabled = true;
    btn.textContent = "Authenticating...";
  }

  try {
    const result = await apiCall("login.php", "POST", {
      role: "student",
      student_id: studentId,
      password: password
    });

    if (result.success) {
      window.location.href = "pages/student_dashboard.php";
    }
  } catch (error) {
    if (btn) {
      btn.disabled = false;
      btn.textContent = "Login as Student";
    }
  }
}


function loginAdmin() {
  const username = document.getElementById("adminUsername").value;
  const password = document.getElementById("adminPassword").value;

  if (!username || !password) {
    showAlert("Please enter username and password", "warning");
    return;
  }

  apiCall("login.php", "POST", {
    role: "admin",
    username: username,
    password: password,
  })
    .then((data) => {
      if (data.success) {
        showAlert("Login successful! Redirecting...", "success");
        setTimeout(() => {
          window.location.href = "pages/admin_dashboard.php";
        }, 500);
      } else {
        showAlert(data.message || "Login failed", "danger");
      }
    })
    .catch((error) => {
      console.error("Login error:", error);
      // Error is already shown by apiCall
    });
}

function loginSuperAdmin() {
  const username = document.getElementById("superAdminUsername").value;
  const password = document.getElementById("superAdminPassword").value;

  if (!username || !password) {
    showAlert("Please enter username and password", "warning");
    return;
  }

  apiCall("login.php", "POST", {
    role: "super_admin",
    username: username,
    password: password,
  })
    .then((data) => {
      if (data.success) {
        showAlert("Login successful! Redirecting...", "success");
        setTimeout(() => {
          window.location.href = "pages/super_admin_dashboard.php";
        }, 500);
      } else {
        showAlert(data.message || "Login failed", "danger");
      }
    })
    .catch((error) => {
      console.error("Login error:", error);
    });
}

// === STUDENT DASHBOARD FUNCTIONS ===
// === STUDENT DASHBOARD FUNCTIONS ===
async function loadStudentDashboard() {
  try {
    const result = await apiCall("student_dashboard.php");
    if (!result || !result.success) {
      showAlert("Failed to connect to university records", "danger");
      return;
    }

    const { student, dues, summary, clearance, certificate } = result;

    // 1. Core Profile Mapping (Aggressive)
    if (student) {
        currentStudent = student;
        const map = {
            "studentName": student.name,
            "welcomeStudentName": student.name,
            "studentId": student.id || student.student_id,
            "studentDepartment": student.department,
            "studentDepartmentHeader": student.department,
            "studentCGPA": student.cgpa
        };
        for (const [id, val] of Object.entries(map)) {
            const el = document.getElementById(id);
            if (el) el.textContent = val || "N/A";
        }
    }

    // 2. Financial Overview
    if (dues) {
        currentDues = dues; // Assign to global variable for payment auto-calculation
        updateDueItem("library", dues.library || { remaining: 0, paid: 0 });
        updateDueItem("hostel", dues.hostel || { remaining: 0, paid: 0 });
        updateDueItem("tuition", dues.tuition || { remaining: 0, paid: 0 });
    }

    if (summary) {
        const sumMap = {
            "totalDue": "₹" + (summary.total_due || 0).toFixed(2),
            "totalPaid": "₹" + (summary.total_paid || 0).toFixed(2),
            "progressPercentage": (summary.progress_percentage || 0) + "%",
            "remainingBalance": "₹" + Math.abs(summary.remaining_balance || 0).toFixed(2)
        };
        for (const [id, val] of Object.entries(sumMap)) {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        }
        
        const progBar = document.getElementById("progressBar");
        if (progBar) {
            progBar.style.width = (summary.progress_percentage || 0) + "%";
            progBar.textContent = (summary.progress_percentage || 0) + "%";
        }
    }

    // 3. Clearance Status & Automation
    const statusBadge = document.getElementById("clearanceStatusBadge");
    if (statusBadge) {
        if (clearance) {
            updateClearanceStatus(clearance);
        } else if (summary && summary.remaining_balance <= 0) {
            statusBadge.textContent = "FINANCIALLY CLEARED";
            statusBadge.className = "status-badge status-approved";
            statusBadge.style.background = "#059669";
            statusBadge.style.color = "white";
        } else {
            statusBadge.textContent = "NO REQUEST YET";
            statusBadge.className = "status-badge status-pending";
        }
    }

    // 4. Action Center
    displayActionNotifications(clearance);
    
    const certBtn = document.getElementById("generateCertBtn");
    if (certBtn) {
        if (certificate && certificate.exists && clearance && clearance.status === "Approved" && clearance.super_admin_action === "Approved") {
            certBtn.disabled = false;
            certBtn.textContent = "Download Certificate";
        } else {
            certBtn.disabled = true;
            if (clearance) {
                if (clearance.status === "Approved" && clearance.super_admin_action !== "Approved") {
                    certBtn.textContent = "Awaiting Super Admin Signature";
                } else if (clearance.status === "Pending") {
                    certBtn.textContent = "Awaiting Department Approval";
                } else {
                    certBtn.textContent = "Awaiting Eligibility";
                }
            } else {
                certBtn.textContent = "Awaiting Eligibility";
            }
        }
    }

    // 5. Background Loading
    loadPaymentHistory().catch(() => {});
    loadNotifications().catch(() => {});
    loadAnnouncements().catch(() => {});
    loadDocuments().catch(() => {});
    if (student && student.qr_token) updateQRCode(student.qr_token);
    if (student) updateProfileUI(student, summary);
    
    // Auto-update payment amount if on payment tab
    if (typeof updatePaymentAmount === 'function') updatePaymentAmount();


  } catch (error) {
    console.error("Dashboard Init Error:", error);
    showAlert("Error initializing academic dashboard", "danger");
  }
}

// Update Student Profile Tab & Academic Profile Fields
function updateProfileUI(student, summary) {
  const map = {
    "profName": student.name,
    "profId": student.id || student.student_id,
    "profDept": student.department,
    "profCGPA": student.cgpa,
    "profEmail": student.email,
    "profPhone": student.phone
  };
  for (const [id, val] of Object.entries(map)) {
    const el = document.getElementById(id);
    if (el) el.textContent = val || "N/A";
  }
  
  const avatarChar = document.getElementById("profileAvatarChar");
  if (avatarChar && student.name) {
    avatarChar.textContent = student.name.trim().charAt(0).toUpperCase();
  }
}

function updateSupportContacts(student) {
    console.log("Updating support contacts for:", student);
    const list = document.getElementById("deptContactList");
    if (!list) return;

    const studentDept = (student && student.department) ? student.department : 'Computer Science';
    
    const deptEmails = {
        'Computer Science': 'cs.dept@university.edu',
        'Engineering': 'eng.dept@university.edu',
        'Business Administration': 'business.admin@university.edu',
        'Medicine': 'med.dept@university.edu',
        'Law': 'law.dept@university.edu',
        'Psychology': 'psych.dept@university.edu',
        'Library': 'library.admin@university.edu'
    };

    const deptEmail = deptEmails[studentDept] || 'admin.support@university.edu';

    list.innerHTML = `
        <div class="contact-item">
            <div class="contact-icon">🏛️</div>
            <div class="contact-info">
                <div class="contact-name">${studentDept} Department Office</div>
                <div class="contact-detail">${deptEmail}</div>
            </div>
        </div>
    `;
}

async function loadAnnouncements() {
    const listWidget = document.getElementById("studentAnnouncementsList");
    const listTab = document.getElementById("studentAnnouncementsTabList");
    const tickerList = document.getElementById("announcementTickerList");
    if (!listWidget && !listTab && !tickerList) {
        console.warn("Announcement elements not found");
        return;
    }

    console.log("Fetching student announcements for current student...");
    try {
        const data = await apiCall("admin_announcements.php?t=" + new Date().getTime());
        console.log("Student Announcements Received:", data);
        
        if (data.success && data.announcements && data.announcements.length > 0) {
            const html = data.announcements.map(ann => `
                <div class="ann-card-item">
                    <div class="ann-card-header">
                        <span class="ann-card-title">📢 ${ann.title}</span>
                        <span class="ann-card-date">${new Date(ann.created_at).toLocaleDateString()}</span>
                    </div>
                    <p class="ann-card-msg">${ann.message}</p>
                    <div class="ann-card-footer">Broadcasted by: <strong>${ann.author}</strong></div>
                </div>
            `).join("");
            if (listWidget) listWidget.innerHTML = html;
            if (listTab) listTab.innerHTML = html;
            if (tickerList) {
                const tickerHtml = data.announcements.map(ann => `
                    <span class="ticker-item"><strong>${ann.title}</strong>: ${ann.message}</span>
                `).join("");
                // Repeat at least 4 times for short lists, and 2 times for longer ones, ensuring perfect infinite scrolling
                const repeatCount = data.announcements.length < 3 ? 4 : 2;
                tickerList.innerHTML = tickerHtml.repeat(repeatCount);
                adjustTickerSpeed(tickerList);
            }
        } else {
            const emptyHtml = `
                <div style="text-align: center; padding: 40px; color: #94a3b8;">
                    <div style="font-size: 40px; margin-bottom: 15px;">🗞️</div>
                    <p>No departmental announcements found for your department.</p>
                </div>
            `;
            if (listWidget) listWidget.innerHTML = emptyHtml;
            if (listTab) listTab.innerHTML = emptyHtml;

            if (tickerList) {
                tickerList.innerHTML = `<span class="ticker-item">No announcements broadcasted yet for your department.</span>`;
                adjustTickerSpeed(tickerList);
            }
        }
    } catch (e) {
        console.error("Announcements Load Error:", e);
        const errorHtml = '<p style="text-align: center; color: #ef4444; padding: 40px;">⚠️ Failed to load announcements. Please refresh.</p>';
        if (listWidget) listWidget.innerHTML = errorHtml;
        if (listTab) listTab.innerHTML = errorHtml;

        if (tickerList) {
            tickerList.innerHTML = `<span class="ticker-item" style="color: #ef4444;">⚠️ Failed to load announcements.</span>`;
            adjustTickerSpeed(tickerList);
        }
    }
}

function displayActionNotifications(clearance) {
  const notificationsArea = document.getElementById("actionNotificationsArea");

  if (!notificationsArea) return;

  notificationsArea.innerHTML = ""; // Clear previous notifications

  if (!clearance) return;

  // Check if there's an admin action (approved or rejected)
  if (
    clearance.status === "Rejected" &&
    clearance.admin_rejection_reason &&
    clearance.admin_timestamp
  ) {
    const rejectionNotif = document.createElement("div");
    rejectionNotif.className = "action-notification notification-rejected";
    rejectionNotif.innerHTML = `
      <div class="notification-header">
        <span class="notification-icon">❌</span>
        <span class="notification-title">Clearance Request Rejected</span>
        <span class="notification-time">${new Date(clearance.admin_timestamp).toLocaleString()}</span>
        <button class="notification-close" onclick="this.parentElement.parentElement.style.display='none';">✕</button>
      </div>
      <div class="notification-body">
        <p><strong>Rejection Reason:</strong></p>
        <p style="background: #fff3e5; padding: 10px; border-radius: 4px; margin-top: 8px;">
          ${clearance.admin_rejection_reason}
        </p>
      </div>
    `;
    notificationsArea.appendChild(rejectionNotif);
  } else if (clearance.status === "Approved" && clearance.admin_timestamp) {
    const approvalNotif = document.createElement("div");
    approvalNotif.className = "action-notification notification-approved";
    approvalNotif.innerHTML = `
      <div class="notification-header">
        <span class="notification-icon">✅</span>
        <span class="notification-title">Clearance Request Approved by Admin</span>
        <span class="notification-time">${new Date(clearance.admin_timestamp).toLocaleString()}</span>
        <button class="notification-close" onclick="this.parentElement.parentElement.style.display='none';">✕</button>
      </div>
      <div class="notification-body">
        <p>Your clearance request has been <strong>approved by the admin</strong>. Waiting for Super Admin final decision.</p>
      </div>
    `;
    notificationsArea.appendChild(approvalNotif);
  }

  // Check for super admin action
  if (clearance.super_admin_action && clearance.super_admin_timestamp) {
    if (clearance.super_admin_action === "Approved") {
      const finalApprovalNotif = document.createElement("div");
      finalApprovalNotif.className =
        "action-notification notification-final-approved";
      finalApprovalNotif.innerHTML = `
        <div class="notification-header">
          <span class="notification-icon">🎉</span>
          <span class="notification-title">Clearance Approved - Final Decision</span>
          <span class="notification-time">${new Date(clearance.super_admin_timestamp).toLocaleString()}</span>
          <button class="notification-close" onclick="this.parentElement.parentElement.style.display='none';">✕</button>
        </div>
        <div class="notification-body">
          <p>Congratulations! Your clearance has been <strong>approved by the Super Admin</strong>. Your certificate is ready for download.</p>
        </div>
      `;
      notificationsArea.appendChild(finalApprovalNotif);
    } else if (clearance.super_admin_action === "Rejected") {
      const finalRejectionNotif = document.createElement("div");
      finalRejectionNotif.className =
        "action-notification notification-final-rejected";
      finalRejectionNotif.innerHTML = `
        <div class="notification-header">
          <span class="notification-icon">⛔</span>
          <span class="notification-title">Clearance Rejected - Final Decision</span>
          <span class="notification-time">${new Date(clearance.super_admin_timestamp).toLocaleString()}</span>
          <button class="notification-close" onclick="this.parentElement.parentElement.style.display='none';">✕</button>
        </div>
        <div class="notification-body">
          <p>Your clearance has been <strong>rejected by the Super Admin</strong>. Please contact the administration for more information.</p>
        </div>
      `;
      notificationsArea.appendChild(finalRejectionNotif);
    }
  }
}

async function loadPaymentHistory() {
  try {
    const result = await apiCall("payment_history.php");

    if (result.success && result.payments) {
      const tableBody = document.getElementById("paymentHistoryTable");

      if (result.payments.length === 0) {
        tableBody.innerHTML =
          '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #999;">No payment history yet</td></tr>';
        return;
      }

      tableBody.innerHTML = result.payments
        .map(
          (payment) => `
        <tr>
          <td>${new Date(payment.payment_date).toLocaleString()}</td>
          <td><span class="badge-payment-type">${payment.payment_type || 'N/A'}</span></td>
          <td>₹${(payment.amount || 0).toFixed(2)}</td>
          <td>₹${(payment.library_paid || 0).toFixed(2)}</td>
          <td>₹${(payment.hostel_paid || 0).toFixed(2)}</td>
          <td>₹${(payment.tuition_paid || 0).toFixed(2)}</td>
          <td>
            <button class="btn btn-small btn-secondary" onclick="window.open('../api/generate_receipt.php?id=${payment.id}', '_blank')" style="padding: 5px 10px; font-size: 11px; cursor: pointer;">
              📄 Receipt
            </button>
          </td>
        </tr>
      `,
        )
        .join("");
    }
  } catch (error) {
    console.error("Failed to load payment history:", error);
  }
}

function updateDueItem(type, due) {
  const dueAmount = document.getElementById(`${type}Due`);
  const paidAmount = document.getElementById(`${type}Paid`);
  const statusBadge = document.getElementById(`${type}Status`);

  // Calculate remaining amount due (amount due - amount paid)
  const remainingDue = due.amount - due.paid;

  dueAmount.textContent = "₹" + remainingDue.toFixed(2);
  paidAmount.textContent = "₹" + due.paid.toFixed(2);

  // If due amount is 0, mark as Paid; otherwise use the status from server
  let displayStatus = due.amount === 0 ? "Paid" : due.status;

  statusBadge.textContent = displayStatus;
  statusBadge.className = `status-badge status-${displayStatus.toLowerCase()}`;
}

function updateClearanceStatus(clearance) {
  const statusDiv = document.getElementById("clearanceStatus");
  const submitBtn = document.getElementById("submitClearanceBtn");
  const rejectionDiv = document.getElementById("rejectionReason");

  if (statusDiv) {
    if (clearance.status === "Pending") {
      statusDiv.innerHTML =
        '<span class="status-badge status-pending">Pending Admin Review</span>';
      if (submitBtn) {
        submitBtn.textContent = "Request Already Submitted";
        submitBtn.disabled = true;
      }
    } else if (clearance.status === "Approved") {
      // Check if super admin has approved
      if (clearance.super_admin_action === "Approved") {
        statusDiv.innerHTML =
          '<span class="status-badge status-approved">✓ Approved</span>';
      } else {
        statusDiv.innerHTML =
          '<span class="status-badge status-approved">Approved - Awaiting Super Admin Final Decision</span>';
      }
      if (submitBtn) {
        submitBtn.textContent = "Request Approved";
        submitBtn.disabled = true;
      }
    } else if (clearance.status === "Rejected") {
      statusDiv.innerHTML = `
        <span class="status-badge status-rejected">Rejected</span>
        <div style="margin-top: 15px; display: flex; gap: 10px; justify-content: center;">
          <button class="btn btn-primary" onclick="showAppealModal()" style="font-size: 14px; padding: 8px 16px;">📤 Appeal / Resubmit</button>
          <button class="btn btn-secondary" onclick="openChat(${clearance.id})" style="font-size: 14px; padding: 8px 16px;">💬 Chat with Admin</button>
        </div>
      `;
      if (clearance.admin_rejection_reason && rejectionDiv) {
        rejectionDiv.innerHTML = `<strong>Rejection Reason:</strong> ${clearance.admin_rejection_reason}`;
        rejectionDiv.style.display = "block";
      }
      if (submitBtn) {
        submitBtn.style.display = "none"; // Hide original button, use Appeal instead
      }
    }
    
    // Add Support Chat Button if a request exists
    if (clearance && clearance.id) {
      const chatBtnHtml = `
        <div style="margin-top: 15px;">
          <button class="btn btn-secondary" onclick="openChat(${clearance.id})" style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
            💬 Chat with Admin
          </button>
        </div>
      `;
      statusDiv.insertAdjacentHTML('beforeend', chatBtnHtml);
    }
  }
}


function updatePaymentAmount() {
  const paymentType = document.getElementById("paymentType").value;
  const paymentAmountInput = document.getElementById("paymentAmount");

  if (!currentDues || !paymentAmountInput) return;

  let amount = 0;

  switch (paymentType) {
    case "all":
      // Calculate total remaining due
      amount = Math.max(
        0,
        currentDues.library.amount -
          currentDues.library.paid +
          (currentDues.hostel.amount - currentDues.hostel.paid) +
          (currentDues.tuition.amount - currentDues.tuition.paid),
      );
      break;
    case "library":
      amount = Math.max(
        0,
        currentDues.library.amount - currentDues.library.paid,
      );
      break;
    case "hostel":
      amount = Math.max(0, currentDues.hostel.amount - currentDues.hostel.paid);
      break;
    case "tuition":
      amount = Math.max(
        0,
        currentDues.tuition.amount - currentDues.tuition.paid,
      );
      break;
  }

  paymentAmountInput.value = amount.toFixed(2);
  paymentAmountInput.placeholder = "₹" + amount.toFixed(2);
}

function preparePayment(type) {
  // 1. Switch to Payment Tab
  const paymentLink = document.querySelector('a[href="#payment"]');
  if (paymentLink) {
    paymentLink.click();
  }

  // 2. Set Payment Type
  const typeSelect = document.getElementById("paymentType");
  if (typeSelect) {
    typeSelect.value = type;
    // Trigger amount update
    updatePaymentAmount();
  }

  // 3. Auto-fill Phone Number if available
  const phoneInput = document.getElementById("paymentPhone");
  if (phoneInput && currentStudent && currentStudent.phone) {
    phoneInput.value = currentStudent.phone;
  }
}

async function processPayment() {
  const paymentType = document.getElementById("paymentType").value;
  const amount = parseFloat(document.getElementById("paymentAmount").value);
  const phone = document.getElementById("paymentPhone").value;


  if (!amount || amount <= 0) {
    showAlert("Please enter a valid amount", "warning");
    return;
  }

  if (!phone) {
    showAlert("Please enter phone number", "warning");
    return;
  }

  // Confirmation dialog
  if (
    !confirm(
      `Are you sure you want to proceed with the payment of ₹${amount.toFixed(2)}?`,
    )
  ) {
    return;
  }

  try {
    const result = await apiCall("process_payment.php", "POST", {
      payment_type: paymentType,
      amount: amount,
      phone_number: phone,
    });

    if (result.success) {
      showAlert("Payment Successful! 🎉", "success");
      document.getElementById("paymentForm").reset();

      // Enable clearance request button if all dues paid
      const submitBtn = document.getElementById("submitClearanceBtn");
      if (submitBtn && result.summary && result.summary.remaining_balance <= 0) {
        submitBtn.disabled = false;
      }

      // Reload dashboard
      loadStudentDashboard();
    }
  } catch (error) {
    console.error("Payment error:", error);
    showAlert("Something's wrong, Payment Unsuccessful ❌", "danger");
  }
}

async function submitClearanceRequest() {
  const message = document.getElementById("clearanceMessage").value;

  try {
    const result = await apiCall("clearance_request.php", "POST", {
      message: message,
    });

    if (result.success) {
      showAlert("Clearance request submitted successfully!", "success");
      document.getElementById("clearanceForm").reset();
      loadStudentDashboard();
    }
  } catch (error) {
    showAlert("Failed to submit request: " + error.message, "danger");
  }
}

// === NOTIFICATION FUNCTIONS ===
function toggleNotifications() {
  const dropdown = document.getElementById("notifDropdown");
  dropdown.classList.toggle("show");
  
  // Mark all as read when opening (simplified logic)
  if (dropdown.classList.contains("show")) {
    const badge = document.getElementById("notifBadge");
    badge.style.display = "none";
  }
}

async function loadNotifications() {
  try {
    const result = await apiCall("get_notifications.php");
    if (result.success) {
      const list = document.getElementById("notifList");
      const badge = document.getElementById("notifBadge");
      const notifications = result.notifications;
      
      const unreadCount = notifications.filter(n => !n.is_read).length;
      if (unreadCount > 0) {
        badge.textContent = unreadCount;
        badge.style.display = "flex";
      } else {
        badge.style.display = "none";
      }
      
      if (notifications.length === 0) {
        list.innerHTML = '<div class="notif-empty">No new notifications</div>';
        return;
      }
      
      list.innerHTML = notifications.map(n => `
        <div class="notif-item ${n.is_read ? '' : 'unread'}">
          <h4>${n.title}</h4>
          <p>${n.message}</p>
          <span class="notif-time">${new Date(n.created_at).toLocaleString()}</span>
        </div>
      `).join("");
    }
  } catch (error) {
    console.error("Failed to load notifications:", error);
  }
}

// === DOCUMENT FUNCTIONS ===
async function uploadDocument() {
  const fileInput = document.getElementById("docFile");
  const docType = document.getElementById("docType").value;
  const uploadBtn = document.getElementById("uploadBtn");
  
  if (!fileInput.files[0]) {
    showAlert("Please select a file", "warning");
    return;
  }
  
  const formData = new FormData();
  formData.append("document", fileInput.files[0]);
  formData.append("document_type", docType);
  
  uploadBtn.disabled = true;
  uploadBtn.textContent = "Uploading...";
  
  try {
    const response = await fetch("../api/upload_document.php", {
      method: "POST",
      body: formData
    });
    const result = await response.json();
    
    if (result.success) {
      showAlert(result.message, "success");
      document.getElementById("uploadForm").reset();
      loadDocuments();
    } else {
      showAlert(result.message, "danger");
    }
  } catch (error) {
    showAlert("Upload failed", "danger");
  } finally {
    uploadBtn.disabled = false;
    uploadBtn.textContent = "Upload Document";
  }
}

async function loadDocuments() {
  try {
    const result = await apiCall("get_documents.php");
    const list = document.getElementById("documentsList");
    
    if (!list) return;
    
    if (result.success && result.documents.length > 0) {
      list.innerHTML = result.documents.map(doc => `
        <div class="doc-item">
          <div class="doc-info">
            <h4>${doc.document_type}</h4>
            <p>Uploaded on: ${new Date(doc.uploaded_at).toLocaleDateString()}</p>
            <span class="status-badge status-${doc.status.toLowerCase()}">${doc.status}</span>
          </div>
          <a href="../${doc.file_path}" target="_blank" class="btn btn-small btn-secondary">View File</a>
        </div>
      `).join("");
    } else {
      list.innerHTML = '<p style="text-align: center; color: #94a3b8; padding: 20px;">No documents uploaded yet</p>';
    }
  } catch (error) {
    console.error("Failed to load documents:", error);
  }
}

// === TIMELINE FUNCTIONS ===
function updateTimelines(clearance, notifications, payments) {
  const activityList = document.getElementById("activityTimeline");
  const timelineCard = document.getElementById("approvalTimelineCard");
  
  // 1. Update Approval Tracker
  if (clearance) {
    timelineCard.style.display = "block";
    resetTimeline();
    
    const stepSubmitted = document.getElementById("step-submitted");
    const stepAdmin = document.getElementById("step-admin");
    const stepSuper = document.getElementById("step-super");
    const stepCompleted = document.getElementById("step-completed");
    
    // Always mark submitted as completed
    markStep(stepSubmitted, "completed");
    
    if (clearance.status === "Approved") {
      markStep(stepAdmin, "completed");
      if (clearance.super_admin_action === "Approved") {
        markStep(stepSuper, "completed");
        markStep(stepCompleted, "completed");
      } else {
        markStep(stepSuper, "active");
      }
    } else if (clearance.status === "Pending") {
      markStep(stepAdmin, "active");
    } else if (clearance.status === "Rejected") {
      markStep(stepAdmin, "active"); // Highlight where it was rejected
    }
  } else {
    timelineCard.style.display = "none";
  }

  // 2. Update Recent Activity (Combine Notifications & Payments)
  let activities = [];
  
  notifications.slice(0, 3).forEach(n => {
    activities.push({
      title: n.title,
      desc: n.message,
      time: n.created_at,
      icon: "🔔"
    });
  });
  
  if (activities.length === 0) {
    activityList.innerHTML = '<div style="text-align: center; color: #94a3b8; padding: 20px;">No recent activity</div>';
    return;
  }

  activityList.innerHTML = activities.sort((a, b) => new Date(b.time) - new Date(a.time)).map(act => `
    <div class="activity-item">
      <div class="activity-content">
        <h4>${act.title}</h4>
        <p>${act.desc}</p>
        <span class="activity-time">${new Date(act.time).toLocaleString()}</span>
      </div>
    </div>
  `).join("");
}

function resetTimeline() {
  document.querySelectorAll(".timeline-step").forEach(step => {
    step.classList.remove("active", "completed");
  });
}

function markStep(stepElement, status) {
  stepElement.classList.add(status);
  // Also mark the connector before it as completed if this is completed
  const connector = stepElement.previousElementSibling;
  if (connector && connector.classList.contains("timeline-connector") && status === "completed") {
    connector.classList.add("completed");
  }
}
function updateQRCode(token) {
  const qrImg = document.getElementById("qrCodeImg");
  if (qrImg) {
    // Using QR Server API for easy QR code generation
    qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${token}`;
  }
}

async function downloadCertificate() {
  try {
    const result = await apiCall("certificate.php", "GET");

    if (result.success && result.certificate) {
      // Create blob from HTML
      const blob = new Blob([result.certificate], { type: "text/html" });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `Certificate_${result.student_name}.html`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);

      showAlert("Certificate downloaded successfully!", "success");
    }
  } catch (error) {
    showAlert("Failed to download certificate: " + error.message, "danger");
  }
}

// === ADMIN DASHBOARD FUNCTIONS ===
async function loadAdminDashboard() { 
  loadNotifications();
  try {
    if (window.IS_LIBRARY_ADMIN) {
      loadLibraryPendingRequests();
      return;
    }
    const result = await apiCall("admin_clearance_list.php");

    if (result.success) {
      displayClearanceRequests(result.requests);
    }
  } catch (error) {
    showAlert("Failed to load requests", "danger");
  }
}

function displayClearanceRequests(requests) {
  // Update stats
  const pending = requests.filter(r => r.status === 'Pending').length;
  const approved = requests.filter(r => r.status === 'Approved').length;
  
  const pendingEl = document.getElementById("pendingCount");
  const approvedEl = document.getElementById("approvedCount");
  const totalEl = document.getElementById("totalRequestsCount");
  
  if (pendingEl) pendingEl.textContent = pending;
  if (approvedEl) approvedEl.textContent = approved;
  if (totalEl) totalEl.textContent = requests.length;

  const container = document.getElementById("requestsContainer");
  container.innerHTML = "";

  if (requests.length === 0) {
    container.innerHTML =
      '<p style="text-align: center; color: #999; padding: 30px;">No clearance requests to review</p>';
    return;
  }

  // Add Bulk Action Bar
  const bulkBar = document.createElement("div");
  bulkBar.className = "bulk-action-bar";
  bulkBar.style = "background: #f1f5f9; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #e2e8f0;";
  bulkBar.innerHTML = `
    <div style="display: flex; align-items: center; gap: 15px;">
        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: bold;">
            <input type="checkbox" onchange="toggleSelectAll(this)" style="width: 18px; height: 18px;"> Select All
        </label>
        <span style="color: #64748b; font-size: 14px;">(Only students with zero dues can be approved)</span>
    </div>
    <button id="bulkApproveBtn" class="btn btn-success" onclick="bulkApprove()" disabled>✓ Bulk Approve (0)</button>
  `;
  container.appendChild(bulkBar);

  requests.forEach((request) => {
    const isEligible = request.payment.total_due <= request.payment.total_paid;
    const requestDiv = document.createElement("div");
    requestDiv.className = "request-item";
    requestDiv.innerHTML = `
            <div class="request-header" style="display: flex; align-items: flex-start; gap: 15px;">
                <input type="checkbox" class="request-checkbox" value="${request.id}" onchange="updateBulkActionButtons()" 
                       ${!isEligible ? 'disabled' : ''} 
                       style="margin-top: 5px; width: 20px; height: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div>
                        <h3 style="margin: 0 0 8px 0;">${request.student.name}</h3>
                        <p style="margin: 0; color: #666; font-size: 14px;">ID: ${request.student.id}</p>
                    </div>
                    <span class="status-badge status-${request.status.toLowerCase()}">${request.status}</span>
                </div>
            </div>
            
            <div class="request-details-grid">
                <div class="request-detail-card">
                    <div class="request-detail-label">📚 Department</div>
                    <div class="request-detail-value">${request.student.department}</div>
                </div>
                <div class="request-detail-card">
                    <div class="request-detail-label">📊 CGPA</div>
                    <div class="request-detail-value">${request.student.cgpa}</div>
                </div>
                <div class="request-detail-card">
                    <div class="request-detail-label">💰 Total Due</div>
                    <div class="request-detail-value">₹${request.payment.total_due.toFixed(2)}</div>
                </div>
                <div class="request-detail-card">
                    <div class="request-detail-label">✅ Total Paid</div>
                    <div class="request-detail-value">₹${request.payment.total_paid.toFixed(2)}</div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h4>Payment & Dues Status</h4>
                </div>
                <div class="due-breakdown">
                    <div class="due-item">
                        <div class="due-title">Library Clearance</div>
                        <div class="due-row">
                            <span class="due-label">Due:</span>
                            <span class="due-value">₹${request.payment.library.due.toFixed(2)}</span>
                        </div>
                        <div class="due-row">
                            <span class="due-label">Paid:</span>
                            <span class="due-value">₹${request.payment.library.paid.toFixed(2)}</span>
                        </div>
                    </div>
                    <div class="due-item">
                        <div class="due-title">Hostel Clearance</div>
                        <div class="due-row">
                            <span class="due-label">Due:</span>
                            <span class="due-value">₹${request.payment.hostel.due.toFixed(2)}</span>
                        </div>
                        <div class="due-row">
                            <span class="due-label">Paid:</span>
                            <span class="due-value">₹${request.payment.hostel.paid.toFixed(2)}</span>
                        </div>
                    </div>
                    <div class="due-item">
                        <div class="due-title">Tuition Fee</div>
                        <div class="due-row">
                            <span class="due-label">Due:</span>
                            <span class="due-value">₹${request.payment.tuition.due.toFixed(2)}</span>
                        </div>
                        <div class="due-row">
                            <span class="due-label">Paid:</span>
                            <span class="due-value">₹${request.payment.tuition.paid.toFixed(2)}</span>
                        </div>
                    </div>
                </div>
                <div class="summary-section">
                    <div class="summary-item">
                        <div class="summary-label">Total Due</div>
                        <div class="summary-value">₹${request.payment.total_due.toFixed(2)}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Total Paid</div>
                        <div class="summary-value">₹${request.payment.total_paid.toFixed(2)}</div>
                    </div>
                </div>
            </div>
            
            <div class="request-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="btn btn-success" onclick="approveRequest(${request.id})" ${!isEligible ? 'disabled' : ''}>
                    ✓ Approve
                </button>
                <button class="btn btn-danger" onclick="showRejectModal(${request.id})">
                    ✗ Reject
                </button>
                ${request.status !== 'Approved' ? `
                <button class="btn btn-info" onclick="openChat(${request.id})">
                    💬 Chat with Student
                </button>` : ''}
                ${request.message ? `
                <button class="btn btn-secondary" onclick="showMessageModal('${request.message.replace(/'/g, "\\'").replace(/\n/g, '\\n').replace(/\r/g, '')}')">
                    📄 View Message
                </button>` : ''}
                ${request.document_path ? `
                <button class="btn btn-secondary" onclick="window.open('../${request.document_path}', '_blank')">
                    📎 View Document
                </button>` : ''}
            </div>
        `;
    container.appendChild(requestDiv);
  });
}


async function approveRequest(requestId) {
  if (!confirm("Are you sure you want to approve this clearance request?")) {
    return;
  }

  try {
    const result = await apiCall("admin_action.php", "POST", {
      request_id: requestId,
      action: "approve",
    });

    if (result.success) {
      showAlert(result.message, "success");
      loadAdminDashboard();
    }
  } catch (error) {
    showAlert("Failed to approve: " + error.message, "danger");
  }
}

function showRejectModal(requestId) {
  const modal = document.getElementById("rejectModal");
  const reasonInput = document.getElementById("adminRejectionReason");
  const submitBtn = document.getElementById("submitRejectBtn");

  reasonInput.value = "";
  submitBtn.onclick = () => rejectRequest(requestId);

  modal.classList.add("show");
}

function closeRejectModal() {
  document.getElementById("rejectModal").classList.remove("show");
}

async function rejectRequest(requestId) {
  const reason = document.getElementById("adminRejectionReason").value;

  if (!reason.trim()) {
    showAlert("Please enter a rejection reason", "warning");
    return;
  }

  try {
    const result = await apiCall("admin_action.php", "POST", {
      request_id: requestId,
      action: "reject",
      reason: reason,
    });

    if (result.success) {
      showAlert(result.message, "success");
      closeRejectModal();
      loadAdminDashboard();
    }
  } catch (error) {
    showAlert("Failed to reject: " + error.message, "danger");
  }
}

async function loadAdminHistory() {
  try {
    if (window.IS_LIBRARY_ADMIN) {
      loadLibraryHistory();
      return;
    }
    const result = await apiCall("admin_history.php");

    if (result.success) {
      displayAdminHistory(result.history);
    }
  } catch (error) {
    showAlert("Failed to load history", "danger");
  }
}

function displayAdminHistory(history) {
  const container = document.getElementById("historyContainer");
  container.innerHTML = "";

  if (history.length === 0) {
    container.innerHTML =
      '<div style="text-align: center; padding: 50px; background: #f9f9f9; border-radius: 8px;"><p style="color: #999; font-size: 16px;">📋 No history available yet</p><p style="color: #ccc; font-size: 12px;">History will appear after approving or rejecting requests</p></div>';
    return;
  }

  const table = document.createElement("table");
  table.className = "history-table";
  table.innerHTML = `
    <thead>
      <tr>
        <th>Student Name</th>
        <th>Student ID</th>
        <th>Status</th>
        <th>Action Date</th>
        <th style="text-align: center;">Details</th>
      </tr>
    </thead>
    <tbody>
      ${history
        .map(
          (item) => `
        <tr style="cursor: pointer;">
          <td>${item.student.name}</td>
          <td>${item.student.id}</td>
          <td><span class="status-badge status-${item.status.toLowerCase()}">${item.status}</span></td>
          <td>${new Date(item.admin_timestamp).toLocaleString()}</td>
          <td style="text-align: center;"><button class="btn btn-small" onclick="showHistoryDetails(${item.id})" style="cursor: pointer; background: #4CAF50; padding: 8px 16px; font-weight: bold;">📄 Details</button></td>
        </tr>
      `,
        )
        .join("")}
    </tbody>
  `;

  container.appendChild(table);
}

// Store history data for details modal
let currentHistoryItem = null;

function showHistoryDetails(requestId) {
  const historyModal = document.getElementById("historyDetailsModal");
  const detailsContent = document.getElementById("historyDetailsContent");

  // Load history and find the item
  apiCall("admin_history.php").then((result) => {
    if (result.success) {
      const item = result.history.find((h) => h.id === requestId);
      if (item) {
        currentHistoryItem = item;

        const rejectionReasonHtml =
          item.status === "Rejected" && item.admin_rejection_reason
            ? `<div class="detail-section">
               <h4>Rejection Reason:</h4>
               <p style="background: #fff3e5; padding: 10px; border-radius: 4px; color: #856404;">
                 ${item.admin_rejection_reason}
               </p>
             </div>`
            : "";

        detailsContent.innerHTML = `
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div class="detail-section">
              <h4>Student Information</h4>
              <p><strong>Name:</strong> ${item.student.name}</p>
              <p><strong>Student ID:</strong> ${item.student.id}</p>
              <p><strong>Email:</strong> ${item.student.email}</p>
              <p><strong>Department:</strong> ${item.student.department}</p>
              <p><strong>CGPA:</strong> ${item.student.cgpa}</p>
            </div>
            
            <div class="detail-section">
              <h4>Request Status</h4>
              <p><strong>Status:</strong> <span class="status-badge status-${item.status.toLowerCase()}">${item.status}</span></p>
              <p><strong>Action Date:</strong> ${new Date(item.admin_timestamp).toLocaleString()}</p>
              <p><strong>Request Date:</strong> ${new Date(item.created_at).toLocaleString()}</p>
            </div>
          </div>
          
          <div class="detail-section">
            <h4>Payment & Dues Details</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
              <div style="background: #f5f5f5; padding: 10px; border-radius: 4px;">
                <strong>Library</strong><br>
                Due: ₹${item.payment.library.due.toFixed(2)}<br>
                Paid: ₹${item.payment.library.paid.toFixed(2)}
              </div>
              <div style="background: #f5f5f5; padding: 10px; border-radius: 4px;">
                <strong>Hostel</strong><br>
                Due: ₹${item.payment.hostel.due.toFixed(2)}<br>
                Paid: ₹${item.payment.hostel.paid.toFixed(2)}
              </div>
              <div style="background: #f5f5f5; padding: 10px; border-radius: 4px;">
                <strong>Tuition</strong><br>
                Due: ₹${item.payment.tuition.due.toFixed(2)}<br>
                Paid: ₹${item.payment.tuition.paid.toFixed(2)}
              </div>
            </div>
            <p style="margin-top: 10px;"><strong>Total Due:</strong> ₹${item.payment.total_due.toFixed(2)}</p>
            <p><strong>Total Paid:</strong> ₹${item.payment.total_paid.toFixed(2)}</p>
          </div>
          
          ${rejectionReasonHtml}
          
          <div class="detail-section">
            <h4>Student Message</h4>
            ${
              item.message
                ? `
              <button class="btn btn-info" onclick="showMessageModal('${item.message.replace(/'/g, "\\'")}')">
                💬 View Message
              </button>
            `
                : `
              <p style="color: #999; font-style: italic;">No message provided</p>
            `
            }
          </div>
        `;

        historyModal.classList.add("show");
      }
    }
  });
}

function closeHistoryDetailsModal() {
  document.getElementById("historyDetailsModal").classList.remove("show");
}

function showMessageModal(message) {
  document.getElementById("messageContent").textContent = message;
  document.getElementById("messageModal").classList.add("show");
}

function closeMessageModal() {
  document.getElementById("messageModal").classList.remove("show");
}

// === SUPER ADMIN DASHBOARD FUNCTIONS ===
let superCachedRequests = [];
async function loadSuperAdminDashboard() {
  try {
    const result = await apiCall("super_admin_list.php");

    if (result.success) {
      superCachedRequests = result.requests || [];
      displayApprovedRequests(superCachedRequests);
    }
  } catch (error) {
    showAlert("Failed to load approved requests", "danger");
  }
}

function displayApprovedRequests(requests) {
  const pending = superCachedRequests.length;
  
  const pendingEl = document.getElementById("superPendingCount");
  const certEl = document.getElementById("superCertCount");
  
  if (pendingEl) pendingEl.textContent = pending;
  
  apiCall("super_admin_history.php").then(result => {
    if (result.success && certEl) {
      const issued = result.history.filter(r => r.super_admin_action === 'Approved').length;
      certEl.textContent = issued;
    }
  });

  const container = document.getElementById("approvedRequestsContainer");
  container.innerHTML = "";

  if (requests.length === 0) {
    container.innerHTML =
      '<p style="text-align: center; color: #999; padding: 30px;">No approved requests waiting for final decision</p>';
    return;
  }

  // Add Bulk Action Bar
  const bulkBar = document.createElement("div");
  bulkBar.className = "bulk-action-bar";
  bulkBar.style = "background: #f1f5f9; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #e2e8f0;";
  bulkBar.innerHTML = `
    <div style="display: flex; align-items: center; gap: 15px;">
        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: bold;">
            <input type="checkbox" onchange="toggleSelectAll(this)" style="width: 18px; height: 18px;"> Select All
        </label>
        <button id="bulkApproveBtn" class="btn btn-success" onclick="bulkApprove()" disabled>📄 Bulk Issue Certificates (0)</button>
    </div>
    <button class="btn btn-download" onclick="generateFinancialReport()" style="background: #1e293b;">📊 Generate Financial Report</button>
  `;
  container.appendChild(bulkBar);

  requests.forEach((request) => {
    const requestDiv = document.createElement("div");
    requestDiv.className = "request-item";
    requestDiv.innerHTML = `
            <div class="request-header" style="display: flex; align-items: flex-start; gap: 15px;">
                <input type="checkbox" class="request-checkbox" value="${request.id}" onchange="updateBulkActionButtons()" 
                       style="margin-top: 5px; width: 20px; height: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div>
                        <h3 style="margin: 0 0 8px 0;">${request.student.name}</h3>
                        <p style="margin: 0; color: #666; font-size: 14px;">ID: ${request.student.id}</p>
                    </div>
                    <span class="status-badge status-${request.status.toLowerCase()}">${request.status}</span>
                </div>
            </div>
            
            <div class="request-details-grid">
                <div class="request-detail-card">
                    <div class="request-detail-label">📚 Department</div>
                    <div class="request-detail-value">${request.student.department}</div>
                </div>
                <div class="request-detail-card">
                    <div class="request-detail-label">📊 CGPA</div>
                    <div class="request-detail-value">${request.student.cgpa}</div>
                </div>
                <div class="request-detail-card">
                    <div class="request-detail-label">💰 Total Due</div>
                    <div class="request-detail-value">₹${request.payment.total_due.toFixed(2)}</div>
                </div>
                <div class="request-detail-card">
                    <div class="request-detail-label">✅ Total Paid</div>
                    <div class="request-detail-value">₹${request.payment.total_paid.toFixed(2)}</div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h4>Payment & Dues Status</h4>
                </div>
                <div class="due-breakdown">
                    <div class="due-item">
                        <div class="due-title">Library Clearance</div>
                        <div class="due-row">
                            <span class="due-label">Due:</span>
                            <span class="due-value">₹${request.payment.library.due.toFixed(2)}</span>
                        </div>
                        <div class="due-row">
                            <span class="due-label">Paid:</span>
                            <span class="due-value">₹${request.payment.library.paid.toFixed(2)}</span>
                        </div>
                    </div>
                    <div class="due-item">
                        <div class="due-title">Hostel Clearance</div>
                        <div class="due-row">
                            <span class="due-label">Due:</span>
                            <span class="due-value">₹${request.payment.hostel.due.toFixed(2)}</span>
                        </div>
                        <div class="due-row">
                            <span class="due-label">Paid:</span>
                            <span class="due-value">₹${request.payment.hostel.paid.toFixed(2)}</span>
                        </div>
                    </div>
                    <div class="due-item">
                        <div class="due-title">Tuition Fee</div>
                        <div class="due-row">
                            <span class="due-label">Due:</span>
                            <span class="due-value">₹${request.payment.tuition.due.toFixed(2)}</span>
                        </div>
                        <div class="due-row">
                            <span class="due-label">Paid:</span>
                            <span class="due-value">₹${request.payment.tuition.paid.toFixed(2)}</span>
                        </div>
                    </div>
                </div>
                <div class="summary-section">
                    <div class="summary-item">
                        <div class="summary-label">Total Due</div>
                        <div class="summary-value">₹${request.payment.total_due.toFixed(2)}</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Total Paid</div>
                        <div class="summary-value">₹${request.payment.total_paid.toFixed(2)}</div>
                    </div>
                </div>
            </div>
            
            <div class="request-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
                ${!request.certificate_issued ? `
                    <button class="btn btn-success" onclick="issueCertificate(${request.id})">
                        📄 Issue Certificate & Approve
                    </button>
                    <button class="btn btn-danger" onclick="openSuperAdminRejectModal(${request.id}, '${request.student.name}')">
                        ❌ Reject Request
                    </button>
                    <button class="btn btn-info" onclick="openChat(${request.id})">
                        💬 Chat with Student
                    </button>
                    ${request.message ? `
                    <button class="btn btn-secondary" onclick="showMessageModal('${request.message.replace(/'/g, "\\'").replace(/\n/g, '\\n').replace(/\r/g, '')}')">
                        📄 View Message
                    </button>` : ''}
                    ${request.document_path ? `
                    <button class="btn btn-secondary" onclick="window.open('../${request.document_path}', '_blank')">
                        📎 View Document
                    </button>` : ''}
                ` : `
                    <button class="btn btn-success" disabled>✓ Certificate Issued</button>
                `}
            </div>
        `;
    container.appendChild(requestDiv);
  });
}

function filterApprovedRequests() {
  const searchInput = document.getElementById("superReqSearchInput");
  const deptFilter = document.getElementById("superReqDeptFilter");
  
  const searchVal = searchInput ? searchInput.value.toLowerCase().trim() : "";
  const deptVal = deptFilter ? deptFilter.value : "all";

  const filtered = superCachedRequests.filter(req => {
    // 1. Search matches student name or ID
    const matchesSearch = 
      req.student.name.toLowerCase().includes(searchVal) ||
      req.student.id.toLowerCase().includes(searchVal);

    // 2. Department filter
    const matchesDept = (deptVal === 'all' || req.student.department === deptVal);

    return matchesSearch && matchesDept;
  });

  displayApprovedRequests(filtered);
}

function resetApprovedFilters() {
  const searchInput = document.getElementById("superReqSearchInput");
  const deptFilter = document.getElementById("superReqDeptFilter");
  
  if (searchInput) searchInput.value = "";
  if (deptFilter) deptFilter.value = "all";
  
  displayApprovedRequests(superCachedRequests);
}


async function issueCertificate(requestId) {
  if (
    !confirm(
      "Are you sure you want to approve this clearance and generate the certificate?",
    )
  ) {
    return;
  }

  try {
    const result = await apiCall("certificate.php", "POST", {
      request_id: requestId,
    });

    if (result.success) {
      showAlert(result.message, "success");
      loadSuperAdminDashboard();
    }
  } catch (error) {
    showAlert("Failed to issue certificate: " + error.message, "danger");
  }
}

function openSuperAdminRejectModal(requestId, studentName) {
  // Store the request ID for later use in submission
  window.currentRejectRequestId = requestId;
  window.currentRejectStudentName = studentName;

  // Clear previous text and show modal
  document.getElementById("superAdminRejectionReason").value = "";
  document.getElementById("superAdminRejectModal").classList.add("show");

  // Set up the submit button
  const submitBtn = document.getElementById("submitSuperAdminRejectBtn");
  submitBtn.onclick = submitSuperAdminReject;
}

function closeSuperAdminRejectModal() {
  document.getElementById("superAdminRejectModal").classList.remove("show");
  window.currentRejectRequestId = null;
  window.currentRejectStudentName = null;
}

async function submitSuperAdminReject() {
  const reason = document
    .getElementById("superAdminRejectionReason")
    .value.trim();

  if (!reason) {
    showAlert("Please enter a rejection reason", "warning");
    return;
  }

  const requestId = window.currentRejectRequestId;

  try {
    const result = await apiCall("super_admin_action.php", "POST", {
      request_id: requestId,
      action: "reject",
      reason: reason,
    });

    if (result.success) {
      closeSuperAdminRejectModal();
      showAlert(`Request Rejected ❌\n\nReason: ${reason}`, "danger");
      loadSuperAdminDashboard();
    }
  } catch (error) {
    showAlert("Failed to reject request: " + error.message, "danger");
  }
}

async function loadSuperAdminHistory() {
  try {
    const result = await apiCall("super_admin_history.php");

    if (result.success) {
      displaySuperAdminHistory(result.history);
    }
  } catch (error) {
    showAlert("Failed to load history", "danger");
  }
}

function displaySuperAdminHistory(history) {
  const container = document.getElementById("superAdminHistoryContainer");
  container.innerHTML = "";

  if (history.length === 0) {
    container.innerHTML =
      '<div style="text-align: center; padding: 50px; background: #f9f9f9; border-radius: 8px;"><p style="color: #999; font-size: 16px;">📋 No history available yet</p><p style="color: #ccc; font-size: 12px;">History will appear after making final decisions on requests</p></div>';
    return;
  }

  const table = document.createElement("table");
  table.className = "history-table";
  table.innerHTML = `
    <thead>
      <tr>
        <th>Student Name</th>
        <th>Student ID</th>
        <th>Decision</th>
        <th>Certificate</th>
        <th>Decision Date</th>
        <th style="text-align: center;">Details</th>
      </tr>
    </thead>
    <tbody>
      ${history
        .map(
          (item) => `
        <tr style="cursor: pointer;">
          <td>${item.student.name}</td>
          <td>${item.student.id}</td>
          <td><span class="status-badge status-${item.super_admin_action.toLowerCase()}">${item.super_admin_action}</span></td>
          <td>${item.certificate_issued ? "✅ Issued" : "⏳ Pending"}</td>
          <td>${new Date(item.super_admin_timestamp).toLocaleString()}</td>
          <td style="text-align: center;"><button class="btn btn-small" onclick="showSuperAdminHistoryDetails(${item.id})" style="cursor: pointer; background: #4CAF50; padding: 8px 16px; font-weight: bold;">📄 Details</button></td>
        </tr>
      `,
        )
        .join("")}
    </tbody>
  `;

  container.appendChild(table);
}

function showSuperAdminHistoryDetails(requestId) {
  const historyModal = document.getElementById("superAdminHistoryDetailsModal");
  const detailsContent = document.getElementById(
    "superAdminHistoryDetailsContent",
  );

  // Load history and find the item
  apiCall("super_admin_history.php").then((result) => {
    if (result.success) {
      const item = result.history.find((h) => h.id === requestId);
      if (item) {
        const certificateHtml = item.certificate_issued
          ? `<div class="detail-section">
               <h4>Certificate Status:</h4>
               <p>✅ <strong>Issued</strong> on ${new Date(item.issued_date).toLocaleDateString()}</p>
             </div>`
          : '<div class="detail-section"><h4>Certificate:</h4><p>⏳ Not yet generated</p></div>';

        detailsContent.innerHTML = `
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div class="detail-section">
              <h4>Student Information</h4>
              <p><strong>Name:</strong> ${item.student.name}</p>
              <p><strong>Student ID:</strong> ${item.student.id}</p>
              <p><strong>Email:</strong> ${item.student.email}</p>
              <p><strong>Department:</strong> ${item.student.department}</p>
              <p><strong>CGPA:</strong> ${item.student.cgpa}</p>
            </div>
            
            <div class="detail-section">
              <h4>Decision Status</h4>
              <p><strong>Decision:</strong> <span class="status-badge status-${item.super_admin_action.toLowerCase()}">${item.super_admin_action}</span></p>
              <p><strong>Decision Date:</strong> ${new Date(item.super_admin_timestamp).toLocaleString()}</p>
              <p><strong>Request Date:</strong> ${new Date(item.created_at).toLocaleString()}</p>
            </div>
          </div>
          
          <div class="detail-section">
            <h4>Payment & Dues Details</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
              <div style="background: #f5f5f5; padding: 10px; border-radius: 4px;">
                <strong>Library</strong><br>
                Due: ₹${item.payment.library.due.toFixed(2)}<br>
                Paid: ₹${item.payment.library.paid.toFixed(2)}
              </div>
              <div style="background: #f5f5f5; padding: 10px; border-radius: 4px;">
                <strong>Hostel</strong><br>
                Due: ₹${item.payment.hostel.due.toFixed(2)}<br>
                Paid: ₹${item.payment.hostel.paid.toFixed(2)}
              </div>
              <div style="background: #f5f5f5; padding: 10px; border-radius: 4px;">
                <strong>Tuition</strong><br>
                Due: ₹${item.payment.tuition.due.toFixed(2)}<br>
                Paid: ₹${item.payment.tuition.paid.toFixed(2)}
              </div>
            </div>
            <p style="margin-top: 10px;"><strong>Total Due:</strong> ₹${item.payment.total_due.toFixed(2)}</p>
            <p><strong>Total Paid:</strong> ₹${item.payment.total_paid.toFixed(2)}</p>
          </div>
          
          ${certificateHtml}
          
          ${
            item.super_admin_action === "Rejected"
              ? `
          <div class="detail-section">
            <h4>Rejection Reason</h4>
            <p style="background: #fff3cd; padding: 10px; border-radius: 4px; border-left: 4px solid #ffc107;">
              ${item.admin_rejection_reason || "No reason provided"}
            </p>
          </div>
          `
              : ""
          }
        `;

        historyModal.classList.add("show");
      }
    }
  });
}

function closeSuperAdminHistoryDetailsModal() {
  document
    .getElementById("superAdminHistoryDetailsModal")
    .classList.remove("show");
}

// === CHAT FUNCTIONS ===
let currentChatRequestId = null;
let chatInterval = null;

function openChat(requestId) {
  const pageAttr = document.body.getAttribute("data-page") || "";
  if (pageAttr === "student-dashboard" || pageAttr === "student_dashboard") {
    const msgLink = document.querySelector('a[href="#messages"]');
    if (msgLink) {
      msgLink.click();
      return;
    }
  }

  currentChatRequestId = requestId;
  const modal = document.getElementById("chatModal");
  if (!modal) {
    createChatModal();
  }
  
  document.getElementById("chatModal").classList.add("show");
  loadMessages();
  
  // Poll for new messages every 5 seconds
  if (chatInterval) clearInterval(chatInterval);
  chatInterval = setInterval(loadMessages, 5000);
}

function closeChat() {
  const modal = document.getElementById("chatModal");
  if (modal) modal.classList.remove("show");
  if (chatInterval) clearInterval(chatInterval);
}

function createChatModal() {
  const modal = document.createElement("div");
  modal.id = "chatModal";
  modal.className = "modal";
  modal.innerHTML = `
    <div class="modal-content chat-modal-content">
      <div class="modal-header">
        <h3>💬 Support Chat</h3>
        <span class="close" onclick="closeChat()">&times;</span>
      </div>
      <div class="modal-body" style="height: 400px; display: flex; flex-direction: column;">
        <div id="chatMessages" class="chat-messages" style="flex: 1; overflow-y: auto; padding: 15px; background: #f8fafc; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e2e8f0;">
          <div class="text-center p-4">Loading messages...</div>
        </div>
        <div class="chat-footer" style="display: flex; gap: 10px;">
          <input type="text" id="chatInput" style="flex: 1; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px;" placeholder="Type your message..." onkeypress="if(event.key === 'Enter') sendMessage()">
          <button class="btn btn-primary" style="padding: 10px 20px;" onclick="sendMessage()">Send</button>
        </div>
      </div>
    </div>
  `;
  document.body.appendChild(modal);
}

async function loadMessages() {
  if (!currentChatRequestId) return;
  
  try {
    const result = await apiCall(`get_messages.php?request_id=${currentChatRequestId}`);
    if (result.success) {
      const container = document.getElementById("chatMessages");
      if (!container) return;
      
      if (result.messages.length === 0) {
        container.innerHTML = '<div style="text-align: center; padding: 40px; color: #94a3b8;">No messages yet. Ask for clarification or help!</div>';
        return;
      }
      
      const pageAttr = document.body.getAttribute("data-page") || "";
      let role = "student";
      if (pageAttr.includes("super-admin")) {
        role = "super_admin";
      } else if (pageAttr.includes("admin")) {
        role = "admin";
      }
      
      container.innerHTML = result.messages.map(m => {
        const isMe = m.sender_role === role;
        return `
          <div class="message ${isMe ? 'me' : 'them'}" style="margin-bottom: 15px; display: flex; flex-direction: column; align-items: ${isMe ? 'flex-end' : 'flex-start'};">
            <div class="message-info" style="font-size: 11px; color: #64748b; margin-bottom: 4px;">${m.sender_name} • ${new Date(m.created_at).toLocaleTimeString()}</div>
            <div class="message-bubble" style="padding: 10px 15px; border-radius: 12px; max-width: 80%; background: ${isMe ? '#2563eb' : 'white'}; color: ${isMe ? 'white' : '#1e293b'}; box-shadow: 0 1px 2px rgba(0,0,0,0.05); border: ${isMe ? 'none' : '1px solid #e2e8f0'};">
              ${m.message}
            </div>
          </div>
        `;
      }).join("");
      
      // Scroll to bottom
      container.scrollTop = container.scrollHeight;
    }
  } catch (error) {
    console.error("Chat load error:", error);
  }
}

async function sendMessage() {
  const input = document.getElementById("chatInput");
  const message = input.value.trim();
  
  if (!message || !currentChatRequestId) return;
  
  try {
    const result = await apiCall("send_message.php", "POST", {
      request_id: currentChatRequestId,
      message: message
    });
    
    if (result.success) {
      input.value = "";
      loadMessages();
    }
  } catch (error) {
    showAlert("Failed to send message", "danger");
  }
}

// === BULK ACTION FUNCTIONS ===
function toggleSelectAll(source) {
  const checkboxes = document.querySelectorAll('.request-checkbox');
  checkboxes.forEach(cb => cb.checked = source.checked);
  updateBulkActionButtons();
}

function updateBulkActionButtons() {
  const checked = document.querySelectorAll('.request-checkbox:checked').length;
  const bulkBtn = document.getElementById("bulkApproveBtn");
  if (bulkBtn) {
    bulkBtn.disabled = checked === 0;
    bulkBtn.innerHTML = `✓ Bulk Approve (${checked})`;
  }
}

async function bulkApprove() {
  const checked = document.querySelectorAll('.request-checkbox:checked');
  const ids = Array.from(checked).map(cb => cb.value);
  
  if (ids.length === 0) return;
  
  if (!confirm(`Are you sure you want to approve ${ids.length} requests at once?`)) return;
  
  try {
    const result = await apiCall("bulk_action.php", "POST", {
      request_ids: ids,
      action: 'approve'
    });
    
    if (result.success) {
      showAlert(result.message, "success");
      const page = document.body.getAttribute("data-page");
      if (page.includes("super-admin")) loadSuperAdminDashboard();
      else loadAdminDashboard();
    }
  } catch (error) {
    showAlert("Bulk action failed", "danger");
  }
}

function closeSuperAdminHistoryDetailsModal() {
  document
    .getElementById("superAdminHistoryDetailsModal")
    .classList.remove("show");
}
function toggleEditProfile(isEditing) {
  const viewModes = document.querySelectorAll(".view-mode");
  const editModes = document.querySelectorAll(".edit-mode");
  const editBtn = document.getElementById("editProfileBtn");
  const cancelBtn = document.getElementById("cancelEditBtn");
  const saveBtn = document.getElementById("saveProfileBtn");

  if (isEditing) {
    const emailEl = document.getElementById("profEmail");
    const phoneEl = document.getElementById("profPhone");
    if (emailEl) document.getElementById("editEmail").value = emailEl.textContent;
    if (phoneEl) document.getElementById("editPhone").value = phoneEl.textContent;
  }

  viewModes.forEach(el => el.style.display = isEditing ? "none" : "inline");
  editModes.forEach(el => el.style.display = isEditing ? "inline-block" : "none");
  if (editBtn) editBtn.style.display = isEditing ? "none" : "inline-block";
  if (cancelBtn) cancelBtn.style.display = isEditing ? "inline-block" : "none";
  if (saveBtn) saveBtn.style.display = isEditing ? "inline-block" : "none";
}

async function saveProfile() {
  const emailInput = document.getElementById("editEmail");
  const phoneInput = document.getElementById("editPhone");
  if (!emailInput || !phoneInput) return;
  
  const email = emailInput.value;
  const phone = phoneInput.value;

  try {
    const result = await apiCall("update_profile.php", "POST", { email, phone });
    if (result.success) {
      showAlert("Profile updated successfully", "success");
      const emailEl = document.getElementById("profEmail");
      const phoneEl = document.getElementById("profPhone");
      if (emailEl) emailEl.textContent = email;
      if (phoneEl) phoneEl.textContent = phone;
      toggleEditProfile(false);
    } else {
      showAlert(result.message || "Failed to update profile", "danger");
    }
  } catch (e) {
    showAlert("Connection error: Could not update profile", "danger");
  }
}

function showAppealModal() { 
    document.getElementById("appealModal").classList.add("show"); 
} 
function closeAppealModal() { 
    document.getElementById("appealModal").classList.remove("show"); 
} 
async function submitAppeal() { 
    const message = document.getElementById("appealMessage").value; 
    const fileInput = document.getElementById("appealDocument");
    
    if (!message.trim()) { 
        showAlert("Please provide a reason or proof for your appeal", "warning"); 
        return; 
    } 
    try { 
        let result;
        if (fileInput && fileInput.files.length > 0) {
            const formData = new FormData();
            formData.append("message", message);
            formData.append("appealDocument", fileInput.files[0]);
            
            const response = await fetch("../api/clearance_request.php", {
                method: "POST",
                body: formData
            });
            result = await response.json();
        } else {
            result = await apiCall("clearance_request.php", "POST", { message: message }); 
        }

        if (result.success) { 
            showAlert("Appeal submitted successfully! Admin will review it.", "success"); 
            closeAppealModal(); 
            loadStudentDashboard(); 
        } else {
            showAlert("Failed to submit appeal: " + (result.message || "Unknown error"), "danger");
        }
    } catch (error) { 
        showAlert("Failed to submit appeal: " + error.message, "danger"); 
    } 
}  
function closeChatModal() { 
    document.getElementById("chatModal").classList.remove("show"); 
    if (chatInterval) clearInterval(chatInterval); 
}

// === SUPER ADMIN DEPARTMENTS SECTION ===
async function loadSuperAdminDepartments() {
  const container = document.getElementById("superDeptCardsContainer");
  if (!container) return;

  try {
    const data = await apiCall("admin_student_list.php");
    if (data && data.success) {
      const stats = data.department_stats || [];
      
      const defaultDepts = [
        { department: 'Computer Science', icon: '💻', color: '#3b82f6' },
        { department: 'Business Administration', icon: '💼', color: '#10b981' },
        { department: 'Engineering', icon: '⚙️', color: '#f59e0b' },
        { department: 'Medicine', icon: '🩺', color: '#ef4444' },
        { department: 'Law', icon: '⚖️', color: '#8b5cf6' },
        { department: 'Psychology', icon: '🧠', color: '#ec4899' },
        { department: 'Library', icon: '📚', color: '#6366f1' }
      ];

      container.innerHTML = defaultDepts.map(d => {
        const dbStat = stats.find(s => s.department === d.department) || {
          total_students: 0,
          pending_clearances: 0,
          approved_clearances: 0
        };

        return `
          <div class="stats-card" style="cursor: pointer; transition: transform 0.2s, box-shadow: 0.2s; border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; display: flex; flex-direction: column; gap: 10px;" 
               onclick="loadSuperDeptStudents('${d.department}')"
               onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.05)';"
               onmouseout="this.style.transform='none'; this.style.boxShadow='none';">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                  <span style="font-size: 24px; padding: 8px; background: ${d.color}15; border-radius: 8px;">${d.icon}</span>
                  <span style="font-size: 11px; font-weight: bold; color: #64748b; background: #f1f5f9; padding: 4px 8px; border-radius: 20px;">
                      Active
                  </span>
              </div>
              <div>
                  <h3 style="font-size: 16px; margin: 0 0 5px 0; color: #0f172a; font-weight: 700;">${d.department}</h3>
                  <div style="display: flex; gap: 15px; font-size: 13px; color: #64748b; margin-top: 10px;">
                      <div>👥 <strong>${dbStat.total_students}</strong> Students</div>
                      <div style="color: #d97706;">⏳ <strong>${dbStat.pending_clearances}</strong> Pending</div>
                      <div style="color: #059669;">✓ <strong>${dbStat.approved_clearances}</strong> Approved</div>
                  </div>
              </div>
          </div>
        `;
      }).join("");

      loadSuperDeptStudents("All");
    }
  } catch (e) {
    console.error("Failed to load Super Admin departments view:", e);
  }
}

let superCachedStudents = [];

function renderSuperStudents(students) {
  const tbody = document.getElementById("superDeptStudentsTableBody");
  if (!tbody) return;

  if (students.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">No student records found matching the filters.</td></tr>`;
    return;
  }

  tbody.innerHTML = students.map(s => {
    let badgeClass = 'status-pending';
    let badgeStyle = '';

    if (s.clearance_status === 'Issued Certificate') {
        badgeClass = '';
        badgeStyle = 'background: #f3e8ff; color: #7e22ce; border: 1px solid #d8b4fe; font-weight: 800; padding: 6px 12px;';
    } else if (s.clearance_status === 'Approved') {
        badgeClass = 'status-approved';
    } else if (s.clearance_status === 'Rejected') {
        badgeClass = 'status-rejected';
    } else if (s.clearance_status === 'Not Started') {
        badgeClass = '';
        badgeStyle = 'background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1;';
    } else if (s.clearance_status.startsWith('Has Dues')) {
        badgeClass = '';
        badgeStyle = 'background: #fee2e2; color: #ef4444; border: 1px solid #fca5a5;';
    }

    return `
      <tr style="border-bottom: 1px solid #f1f5f9;">
          <td style="padding: 12px 10px; font-weight: bold; color: #1e293b;">${s.student_id}</td>
          <td style="padding: 12px 10px;">${s.name}</td>
          <td style="padding: 12px 10px; color: #475569;">${s.department}</td>
          <td style="padding: 12px 10px; color: #64748b;">${s.email}</td>
          <td style="padding: 12px 10px; font-weight: 500;">${s.cgpa.toFixed(2)}</td>
          <td style="padding: 12px 10px;">
              <span class="status-badge ${badgeClass}" style="${badgeStyle}">
                  ${s.clearance_status}
              </span>
          </td>
          <td style="padding: 12px 10px; text-align: center;">
              <button onclick="removeStudent(${s.id}, '${s.name.replace(/'/g, "\\'")}')" 
                      style="background: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: background 0.2s;"
                      onmouseover="this.style.background='#fecaca'"
                      onmouseout="this.style.background='#fee2e2'">
                  Remove
              </button>
          </td>
      </tr>
    `;
  }).join("");
}

async function loadSuperDeptStudents(department) {
  const tbody = document.getElementById("superDeptStudentsTableBody");
  const title = document.getElementById("deptRosterTitle");
  const select = document.getElementById("superDeptSelect");
  
  if (!tbody) return;
  if (select) select.value = department;
  if (title) {
    title.textContent = department === 'All' 
      ? "Institutional Student Roster (All Departments)" 
      : `Student Roster: ${department}`;
  }

  // Reset filter inputs upon department change
  const searchInput = document.getElementById("superStudentSearchInput");
  const statusFilter = document.getElementById("superStudentStatusFilter");
  if (searchInput) searchInput.value = "";
  if (statusFilter) statusFilter.value = "all";

  tbody.innerHTML = `
    <tr>
        <td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">
            <div style="width: 30px; height: 30px; border: 3px solid #f1f5f9; border-top: 3px solid #eab308; border-radius: 50%; margin: 0 auto 10px; animation: spin 1s linear infinite;"></div>
            Fetching student records...
        </td>
    </tr>
  `;

  try {
    const url = department === 'All' ? "admin_student_list.php" : `admin_student_list.php?department=${encodeURIComponent(department)}`;
    const data = await apiCall(url);
    if (data && data.success) {
      superCachedStudents = data.students || [];
      renderSuperStudents(superCachedStudents);
    } else {
      tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 40px; color: #ef4444;">Failed to fetch student roster.</td></tr>`;
    }
  } catch (e) {
    console.error("Failed to load department student list:", e);
    tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 40px; color: #ef4444;">Connection error while loading roster.</td></tr>`;
  }

  // Cascading call to load administrators
  loadSuperDeptAdmins(department);
}

function filterSuperStudents() {
  const searchInput = document.getElementById("superStudentSearchInput");
  const statusFilter = document.getElementById("superStudentStatusFilter");
  
  const searchVal = searchInput ? searchInput.value.toLowerCase().trim() : "";
  const statusVal = statusFilter ? statusFilter.value : "all";

  const filtered = superCachedStudents.filter(s => {
    // 1. Search matches name, ID, or email
    const matchesSearch = 
      s.name.toLowerCase().includes(searchVal) ||
      s.student_id.toLowerCase().includes(searchVal) ||
      s.email.toLowerCase().includes(searchVal);

    // 2. Status filter matches
    let matchesStatus = true;
    const status = s.clearance_status.toLowerCase();

    if (statusVal === 'issued') {
      matchesStatus = (status === 'issued certificate');
    } else if (statusVal === 'approved') {
      matchesStatus = (status === 'approved');
    } else if (statusVal === 'pending') {
      matchesStatus = (status === 'pending');
    } else if (statusVal === 'dues') {
      matchesStatus = status.startsWith('has dues');
    } else if (statusVal === 'not_started') {
      matchesStatus = (status === 'not started');
    }

    return matchesSearch && matchesStatus;
  });

  renderSuperStudents(filtered);
}

function resetSuperFilters() {
  const searchInput = document.getElementById("superStudentSearchInput");
  const statusFilter = document.getElementById("superStudentStatusFilter");
  
  if (searchInput) searchInput.value = "";
  if (statusFilter) statusFilter.value = "all";
  
  renderSuperStudents(superCachedStudents);
}

// === SUPER ADMIN ASSIGN/MANAGE logic ===
async function loadSuperDeptAdmins(department) {
  const tbody = document.getElementById("superDeptAdminsTableBody");
  const title = document.getElementById("deptAdminsTitle");
  if (!tbody) return;

  if (title) {
    title.textContent = department === 'All'
      ? "Department Administrators (All Units)"
      : `Department Administrators: ${department}`;
  }

  tbody.innerHTML = `
    <tr>
        <td colspan="4" style="text-align: center; padding: 30px; color: #94a3b8;">
            <div style="width: 25px; height: 25px; border: 3px solid #f1f5f9; border-top: 3px solid #3b82f6; border-radius: 50%; margin: 0 auto 10px; animation: spin 1s linear infinite;"></div>
            Fetching administrators...
        </td>
    </tr>
  `;

  try {
    const url = department === 'All' ? "super_admin_manage.php?action=get_admins" : `super_admin_manage.php?action=get_admins&department=${encodeURIComponent(department)}`;
    const data = await apiCall(url);
    if (data && data.success) {
      if (data.admins.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" style="text-align: center; padding: 30px; color: #94a3b8;">No administrators assigned to this unit.</td></tr>`;
        return;
      }

      tbody.innerHTML = data.admins.map(a => `
        <tr style="border-bottom: 1px solid #f1f5f9;">
            <td style="padding: 12px 10px; font-weight: bold; color: #0f172a;">👤 ${a.username}</td>
            <td style="padding: 12px 10px; color: #475569;">${a.department}</td>
            <td style="padding: 12px 10px;"><span style="background: #eef2ff; color: #4f46e5; border: 1px solid #e0e7ff; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold;">${a.role}</span></td>
            <td style="padding: 12px 10px; text-align: center;">
                <button onclick="removeAdmin(${a.id}, '${a.username}')" 
                        style="background: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: background 0.2s;"
                        onmouseover="this.style.background='#fecaca'"
                        onmouseout="this.style.background='#fee2e2'">
                    Remove
                </button>
            </td>
        </tr>
      `).join("");
    } else {
      tbody.innerHTML = `<tr><td colspan="4" style="text-align: center; padding: 30px; color: #ef4444;">Failed to load administrators.</td></tr>`;
    }
  } catch (e) {
    console.error("Failed to fetch admins roster:", e);
    tbody.innerHTML = `<tr><td colspan="4" style="text-align: center; padding: 30px; color: #ef4444;">Connection error loading administrators.</td></tr>`;
  }
}

// Student Modal Controls
function openAddStudentModal() {
  const modal = document.getElementById("addStudentModal");
  if (modal) modal.classList.add("show");
}

function closeAddStudentModal() {
  const modal = document.getElementById("addStudentModal");
  if (modal) {
    modal.classList.remove("show");
    document.getElementById("addStudentForm").reset();
  }
}

// Admin Modal Controls
function openAddAdminModal() {
  const modal = document.getElementById("addAdminModal");
  if (modal) modal.classList.add("show");
}

function closeAddAdminModal() {
  const modal = document.getElementById("addAdminModal");
  if (modal) {
    modal.classList.remove("show");
    document.getElementById("addAdminForm").reset();
  }
}

// Form Submissions
async function submitAddStudent(event) {
  event.preventDefault();
  const student_id = document.getElementById("newStudentId").value.trim();
  const name = document.getElementById("newStudentName").value.trim();
  const email = document.getElementById("newStudentEmail").value.trim();
  const phone = document.getElementById("newStudentPhone").value.trim();
  const department = document.getElementById("newStudentDept").value;
  const cgpa = document.getElementById("newStudentCgpa").value;

  try {
    const result = await apiCall("super_admin_manage.php?action=add_student", "POST", {
      student_id, name, email, phone, department, cgpa
    });

    if (result && result.success) {
      showAlert(result.message || "Student added successfully!", "success");
      closeAddStudentModal();
      loadSuperAdminDepartments(); // Reload roster & aggregates
    } else {
      showAlert(result.message || "Failed to add student.", "danger");
    }
  } catch (e) {
    console.error(e);
    showAlert("Failed to connect to administrative API", "danger");
  }
}

async function submitAddAdmin(event) {
  event.preventDefault();
  const username = document.getElementById("newAdminUsername").value.trim();
  const password = document.getElementById("newAdminPassword").value;
  const department = document.getElementById("newAdminDept").value;

  try {
    const result = await apiCall("super_admin_manage.php?action=add_admin", "POST", {
      username, password, department
    });

    if (result && result.success) {
      showAlert(result.message || "Administrator assigned successfully!", "success");
      closeAddAdminModal();
      // Reload admins table
      const activeDept = document.getElementById("superDeptSelect").value;
      loadSuperDeptAdmins(activeDept);
    } else {
      showAlert(result.message || "Failed to assign administrator.", "danger");
    }
  } catch (e) {
    console.error(e);
    showAlert("Failed to connect to administrative API", "danger");
  }
}

// Deletions
async function removeStudent(id, name) {
  if (!confirm(`⚠️ WARNING: Are you sure you want to permanently remove student "${name}"?\nThis will cascadingly delete all of their academic records, clearance requests, support chat messages, payments, certificates, and outstanding dues!`)) {
    return;
  }

  try {
    const result = await apiCall("super_admin_manage.php?action=remove_student", "POST", { id });
    if (result && result.success) {
      showAlert(result.message || "Student successfully removed", "success");
      loadSuperAdminDepartments();
    } else {
      showAlert(result.message || "Failed to remove student", "danger");
    }
  } catch (e) {
    console.error(e);
    showAlert("Error connecting to administrative API", "danger");
  }
}

async function removeAdmin(id, username) {
  if (!confirm(`Are you sure you want to permanently remove administrator "${username}"?\nThey will lose access to their departmental clearance portal immediately.`)) {
    return;
  }

  try {
    const result = await apiCall("super_admin_manage.php?action=remove_admin", "POST", { id });
    if (result && result.success) {
      showAlert(result.message || "Administrator successfully removed", "success");
      const activeDept = document.getElementById("superDeptSelect").value;
      loadSuperDeptAdmins(activeDept);
    } else {
      showAlert(result.message || "Failed to remove administrator", "danger");
    }
  } catch (e) {
    console.error(e);
    showAlert("Error connecting to administrative API", "danger");
  }
}

// === LIBRARY SYSTEM FUNCTIONS ===

// 1. Student Portal - Load Books
async function loadLibraryBooks() {
  try {
    const result = await apiCall("library_books.php", "GET");
    const tbody = document.getElementById("libraryBooksTable");
    if (!tbody) return;

    if (result.success && result.books) {
      if (result.books.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #999;">No books available in library catalog</td></tr>';
        return;
      }

      tbody.innerHTML = result.books.map(book => {
        const isAvailable = book.available_copies > 0;
        const buttonHtml = isAvailable 
          ? `<button class="btn btn-small btn-primary" onclick="openBorrowModal(${book.id}, '${book.title.replace(/'/g, "\\'")}')">Borrow</button>`
          : `<button class="btn btn-small btn-secondary" disabled style="opacity: 0.5; background: #94a3b8;">Out of Stock</button>`;
        
        const cleanIsbn = book.isbn.replace(/-/g, '').trim();
        const coverUrl = `https://covers.openlibrary.org/b/isbn/${cleanIsbn}-M.jpg?default=false`;
        const svgPlaceholder = `data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='50' height='70' viewBox='0 0 50 70'><rect width='50' height='70' fill='%23f1f5f9' rx='4'/><text x='50%' y='50%' dominant-baseline='middle' text-anchor='middle' font-size='20' fill='%2394a3b8'>📚</text></svg>`;
        const imgHtml = `<img src="${coverUrl}" onerror="this.onerror=null; this.src=\`${svgPlaceholder}\`;" style="width: 45px; height: 60px; object-fit: cover; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; display: block;" alt="Book Cover">`;

        return `
          <tr>
            <td>${imgHtml}</td>
            <td style="font-weight: bold; color: #1e293b;">${book.title}</td>
            <td>${book.author}</td>
            <td><code>${book.isbn}</code></td>
            <td style="font-weight: bold;">${book.available_copies} / ${book.copies}</td>
            <td>${buttonHtml}</td>
          </tr>
        `;
      }).join("");
    }
  } catch (error) {
    console.error("Failed to load library books:", error);
  }
}

// 2. Student Portal - Search Books
async function searchLibraryBooks() {
  const query = document.getElementById("bookSearchInput").value;
  try {
    const result = await apiCall(`library_books.php?search=${encodeURIComponent(query)}`, "GET");
    const tbody = document.getElementById("libraryBooksTable");
    if (!tbody) return;

    if (result.success && result.books) {
      if (result.books.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #999;">No matching books found</td></tr>';
        return;
      }

      tbody.innerHTML = result.books.map(book => {
        const isAvailable = book.available_copies > 0;
        const buttonHtml = isAvailable 
          ? `<button class="btn btn-small btn-primary" onclick="openBorrowModal(${book.id}, '${book.title.replace(/'/g, "\\'")}')">Borrow</button>`
          : `<button class="btn btn-small btn-secondary" disabled style="opacity: 0.5; background: #94a3b8;">Out of Stock</button>`;
        
        const cleanIsbn = book.isbn.replace(/-/g, '').trim();
        const coverUrl = `https://covers.openlibrary.org/b/isbn/${cleanIsbn}-M.jpg?default=false`;
        const svgPlaceholder = `data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='50' height='70' viewBox='0 0 50 70'><rect width='50' height='70' fill='%23f1f5f9' rx='4'/><text x='50%' y='50%' dominant-baseline='middle' text-anchor='middle' font-size='20' fill='%2394a3b8'>📚</text></svg>`;
        const imgHtml = `<img src="${coverUrl}" onerror="this.onerror=null; this.src=\`${svgPlaceholder}\`;" style="width: 45px; height: 60px; object-fit: cover; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; display: block;" alt="Book Cover">`;

        return `
          <tr>
            <td>${imgHtml}</td>
            <td style="font-weight: bold; color: #1e293b;">${book.title}</td>
            <td>${book.author}</td>
            <td><code>${book.isbn}</code></td>
            <td style="font-weight: bold;">${book.available_copies} / ${book.copies}</td>
            <td>${buttonHtml}</td>
          </tr>
        `;
      }).join("");
    }
  } catch (error) {
    console.error("Search failed:", error);
  }
}

// 3. Student Portal - Borrow Book Modal Actions
function openBorrowModal(bookId, bookTitle) {
  const modal = document.getElementById("borrowModal");
  if (!modal) return;
  document.getElementById("borrowBookId").value = bookId;
  document.getElementById("borrowBookTitle").textContent = bookTitle;
  
  const returnInput = document.getElementById("borrowReturnDate");
  if (returnInput) {
    const today = new Date();
    
    // Default is 14 days from now
    const defaultDate = new Date(today);
    defaultDate.setDate(today.getDate() + 14);
    
    const formatDate = (date) => {
      const yyyy = date.getFullYear();
      let mm = date.getMonth() + 1;
      let dd = date.getDate();
      let hh = date.getHours();
      let min = date.getMinutes();
      if (mm < 10) mm = '0' + mm;
      if (dd < 10) dd = '0' + dd;
      if (hh < 10) hh = '0' + hh;
      if (min < 10) min = '0' + min;
      return `${yyyy}-${mm}-${dd}T${hh}:${min}`;
    };
    
    // Allow any date/time for flexible testing (remove min/max limits)
    returnInput.value = formatDate(defaultDate);
    
    returnInput.onchange = updateCalculatedDuration;
    returnInput.oninput = updateCalculatedDuration;
    
    updateCalculatedDuration();
  }
  
  modal.style.display = "block";
}

function closeBorrowModal() {
  const modal = document.getElementById("borrowModal");
  if (modal) modal.style.display = "none";
}

function updateCalculatedDuration() {
  const returnInput = document.getElementById("borrowReturnDate");
  const textElement = document.getElementById("calculatedDurationText");
  if (!returnInput || !textElement) return;

  const today = new Date();
  const selectedDate = new Date(returnInput.value);

  const diffTime = selectedDate.getTime() - today.getTime();
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

  if (isNaN(diffDays)) {
    textElement.textContent = "Please select a valid return date & time.";
    textElement.style.color = "#64748b";
  } else if (diffDays <= 0) {
    const absDays = Math.abs(diffDays);
    textElement.textContent = `Selected duration: ${diffDays} Days (Overdue by ${absDays} day${absDays !== 1 ? 's' : ''})`;
    textElement.style.color = "#ef4444";
  } else {
    textElement.textContent = `Selected duration: ${diffDays} Day${diffDays > 1 ? 's' : ''}`;
    textElement.style.color = "#16a34a";
  }
}

async function submitBorrowRequest() {
  const bookId = document.getElementById("borrowBookId").value;
  const returnInput = document.getElementById("borrowReturnDate");
  
  if (!returnInput || !returnInput.value) {
    showAlert("Please select a return date & time", "danger");
    return;
  }
  
  const today = new Date();
  const selectedDate = new Date(returnInput.value);
  
  const diffTime = selectedDate.getTime() - today.getTime();
  const duration = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  
  if (isNaN(duration)) {
    showAlert("Invalid return date selected", "danger");
    return;
  }
  
  // Format selected datetime to MySQL TIMESTAMP format YYYY-MM-DD HH:MM:SS
  const mysqlDateTime = returnInput.value.replace('T', ' ') + ':00';
  
  closeBorrowModal();
  
  try {
    const result = await apiCall("library_loans.php", "POST", { 
      book_id: bookId, 
      duration_days: duration,
      due_date: mysqlDateTime
    });
    if (result.success) {
      showAlert(result.message, "success");
      loadLibraryBooks();
      loadStudentLoans();
      if (typeof loadStudentDashboard === 'function') {
        loadStudentDashboard();
      }
    } else {
      showAlert(result.message || "Failed to submit borrow request", "danger");
    }
  } catch (error) {
    showAlert("Borrow failed: " + error.message, "danger");
  }
}

// 4. Student Portal - Load Student Borrow Loans
async function loadStudentLoans() {
  try {
    const result = await apiCall("library_loans.php", "GET");
    const tbody = document.getElementById("myLoansTable");
    if (!tbody) return;

    if (result.success && result.loans) {
      if (result.loans.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 20px; color: #999;">You have not borrowed any books yet</td></tr>';
        return;
      }

      tbody.innerHTML = result.loans.map(loan => {
        const borrowDateStr = new Date(loan.borrow_date).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
        const dueDateStr = new Date(loan.due_date).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
        const returnDateStr = loan.return_date ? new Date(loan.return_date).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' }) : "-";
        const fineStr = "₹" + parseFloat(loan.fine_amount).toFixed(2);
        
        let statusBadgeClass = "status-active";
        if (loan.status === "Returned") statusBadgeClass = "status-approved";
        if (loan.status === "Overdue") statusBadgeClass = "status-rejected";
        if (loan.status.startsWith("Pending")) statusBadgeClass = "status-pending";

        let actionHtml = "-";
        if (loan.status === 'Active' || loan.status === 'Overdue') {
          actionHtml = `
            <button class="btn btn-small btn-secondary" onclick="renewLoan(${loan.id})" style="padding: 5px 10px; font-size: 11px; margin-right: 5px;">Renew</button>
            <button class="btn btn-small btn-danger" onclick="requestReturn(${loan.id})" style="padding: 5px 10px; font-size: 11px; background: #ef4444; border: none; color: white;">Return</button>
          `;
        }

        return `
          <tr>
            <td style="font-weight: bold; color: #1e293b;">${loan.title}</td>
            <td>${loan.author}</td>
            <td><code>${loan.isbn}</code></td>
            <td>${borrowDateStr}</td>
            <td>${dueDateStr}</td>
            <td>${returnDateStr}</td>
            <td style="font-weight: bold; color: ${loan.fine_amount > 0 ? '#dc2626' : '#16a34a'};">${fineStr}</td>
            <td><span class="status-badge ${statusBadgeClass}">${loan.status}</span></td>
            <td>${actionHtml}</td>
          </tr>
        `;
      }).join("");
    }
  } catch (error) {
    console.error("Failed to load loans:", error);
  }
}

// 5. Admin Portal - Load Admin Library Catalog
async function loadAdminLibraryCatalog() {
  try {
    const result = await apiCall("library_books.php", "GET");
    if (result.success && result.books) {
      window.adminBooks = result.books;
    } else {
      window.adminBooks = [];
    }
    filterAdminBooks();
  } catch (error) {
    console.error("Failed to load library catalog:", error);
    window.adminBooks = [];
    filterAdminBooks();
  }
}

// 5a. Admin Portal - Filter Library Book Catalog
function filterAdminBooks() {
  const tbody = document.getElementById("adminBooksTableBody");
  if (!tbody) return;

  const books = window.adminBooks || [];
  const query = (document.getElementById("adminBookSearchInput")?.value || "").toLowerCase().trim();
  const filter = document.getElementById("adminBookStatusFilter")?.value || "all";

  let filtered = books;

  if (query) {
    filtered = filtered.filter(book => 
      book.title.toLowerCase().includes(query) ||
      book.author.toLowerCase().includes(query) ||
      book.isbn.toLowerCase().includes(query)
    );
  }

  if (filter === "available") {
    filtered = filtered.filter(book => parseInt(book.available_copies) > 0);
  } else if (filter === "out_of_stock") {
    filtered = filtered.filter(book => parseInt(book.available_copies) <= 0);
  }

  if (filtered.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #94a3b8;">No books match criteria</td></tr>';
    return;
  }

  tbody.innerHTML = filtered.map(book => {
    const cleanIsbn = book.isbn.replace(/-/g, '').trim();
    const coverUrl = `https://covers.openlibrary.org/b/isbn/${cleanIsbn}-M.jpg?default=false`;
    const svgPlaceholder = `data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='50' height='70' viewBox='0 0 50 70'><rect width='50' height='70' fill='%23f1f5f9' rx='4'/><text x='50%' y='50%' dominant-baseline='middle' text-anchor='middle' font-size='20' fill='%2394a3b8'>📚</text></svg>`;
    const imgHtml = `<img src="${coverUrl}" onerror="this.onerror=null; this.src=\`${svgPlaceholder}\`;" style="width: 45px; height: 60px; object-fit: cover; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; display: block;" alt="Book Cover">`;

    return `
      <tr>
        <td>${imgHtml}</td>
        <td style="font-weight: bold; color: #1e293b;">${book.title}</td>
        <td>${book.author}</td>
        <td><code>${book.isbn}</code></td>
        <td>${book.copies}</td>
        <td>${book.available_copies}</td>
        <td style="text-align: center;">
          <button class="btn btn-small btn-danger" onclick="deleteBook(${book.id})" style="padding: 5px 10px; font-size: 11px;">✗ Delete</button>
        </td>
      </tr>
    `;
  }).join("");
}

// 6. Admin Portal - Add Book Modals
function openAddBookModal() {
  const modal = document.getElementById("addBookModal");
  if (modal) {
    document.getElementById("addBookForm").reset();
    modal.classList.add("show");
  }
}

// 6a. Admin Portal - Close Book Modal
function closeAddBookModal() {
  const modal = document.getElementById("addBookModal");
  if (modal) {
    modal.classList.remove("show");
  }
}

// 6b. Admin Portal - Submit Add Book Request
async function submitAddBook(event) {
  event.preventDefault();
  const title = document.getElementById("newBookTitle").value;
  const author = document.getElementById("newBookAuthor").value;
  const isbn = document.getElementById("newBookIsbn").value;
  const copies = document.getElementById("newBookCopies").value;

  try {
    const result = await apiCall("library_books.php", "POST", {
      title,
      author,
      isbn,
      copies
    });

    if (result.success) {
      showAlert(result.message, "success");
      closeAddBookModal();
      loadAdminLibraryCatalog();
    } else {
      showAlert(result.message || "Failed to add book", "danger");
    }
  } catch (error) {
    showAlert("Failed: " + error.message, "danger");
  }
}

// 7. Admin Portal - Delete Book
async function deleteBook(bookId) {
  if (!confirm("Are you sure you want to permanently delete this book from catalog?")) return;
  try {
    const result = await apiCall("library_books.php", "DELETE", { id: bookId });
    if (result.success) {
      showAlert(result.message, "success");
      loadAdminLibraryCatalog();
    } else {
      showAlert(result.message || "Failed to delete book", "danger");
    }
  } catch (error) {
    showAlert("Delete failed: " + error.message, "danger");
  }
}

// 8. Admin Portal - Load Borrow Loans Queue
async function loadAdminLoans() {
  try {
    const result = await apiCall("library_loans.php", "GET");
    if (result.success && result.loans) {
      window.adminLoans = result.loans.filter(l => l.status !== 'Returned');
    } else {
      window.adminLoans = [];
    }
    filterAdminLoans();
  } catch (error) {
    console.error("Failed to load admin loans queue:", error);
    window.adminLoans = [];
    filterAdminLoans();
  }
}

// 8a. Admin Portal - Filter Borrow Loans
function filterAdminLoans() {
  const tbody = document.getElementById("adminLoansTableBody");
  if (!tbody) return;

  const loans = window.adminLoans || [];
  const query = (document.getElementById("adminLoanSearchInput")?.value || "").toLowerCase().trim();
  const filter = document.getElementById("adminLoanStatusFilter")?.value || "all";

  let filtered = loans;

  if (query) {
    filtered = filtered.filter(loan => 
      loan.student_name.toLowerCase().includes(query) ||
      loan.student_uid.toLowerCase().includes(query) ||
      loan.title.toLowerCase().includes(query) ||
      loan.isbn.toLowerCase().includes(query)
    );
  }

  if (filter !== "all") {
    filtered = filtered.filter(loan => loan.status === filter);
  }

  if (filtered.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #94a3b8;">No active loans match criteria</td></tr>';
    return;
  }

  tbody.innerHTML = filtered.map(loan => {
    const borrowDateStr = new Date(loan.borrow_date).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
    const dueDateStr = new Date(loan.due_date).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
    const returnDateStr = loan.return_date ? new Date(loan.return_date).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' }) : "-";
    const fineStr = "₹" + parseFloat(loan.fine_amount).toFixed(2);
    
    let statusBadgeClass = "status-active";
    if (loan.status === "Returned") statusBadgeClass = "status-approved";
    if (loan.status === "Overdue") statusBadgeClass = "status-rejected";
    if (loan.status.startsWith("Pending")) statusBadgeClass = "status-pending";

    const actionHtml = loan.status !== 'Returned' && !loan.status.startsWith("Pending")
      ? `<button class="btn btn-small btn-success" onclick="returnBook(${loan.id})" style="padding: 5px 10px; font-size: 11px;">✓ Check In</button>`
      : `-`;

    return `
      <tr>
        <td>
          <div style="font-weight: bold; color: #1e293b;">${loan.student_name}</div>
          <div style="color: #64748b; font-size: 11px;">ID: ${loan.student_uid}</div>
        </td>
        <td>
          <div style="font-weight: bold; color: #1e293b;">${loan.title}</div>
          <div style="color: #64748b; font-size: 11px;">ISBN: ${loan.isbn}</div>
        </td>
        <td>${borrowDateStr}</td>
        <td>${dueDateStr}</td>
        <td style="font-weight: bold; color: ${loan.fine_amount > 0 ? '#dc2626' : '#16a34a'};">${fineStr}</td>
        <td><span class="status-badge ${statusBadgeClass}">${loan.status}</span></td>
        <td style="text-align: center;">${actionHtml}</td>
      </tr>
    `;
  }).join("");
}

// 9. Admin Portal - Check In / Return Book
async function returnBook(loanId) {
  if (!confirm("Are you sure you want to check in/return this borrowed book?")) return;
  try {
    const result = await apiCall("library_loans.php", "PUT", { loan_id: loanId });
    if (result.success) {
      showAlert(result.message, "success");
      loadAdminLoans();
      loadAdminLibraryCatalog();
    } else {
      showAlert(result.message || "Failed to return book", "danger");
    }
  } catch (error) {
    showAlert("Return failed: " + error.message, "danger");
  }
}

// 10. Student Portal - Renew Book Loan
async function renewLoan(loanId) {
  if (!confirm("Are you sure you want to renew this book loan for 7 additional days?")) return;
  try {
    const result = await apiCall("library_loans.php?action=renew", "POST", { loan_id: loanId });
    if (result.success) {
      showAlert(result.message, "success");
      loadStudentLoans();
    } else {
      showAlert(result.message || "Failed to renew book loan", "danger");
    }
  } catch (error) {
    showAlert("Renewal failed: " + error.message, "danger");
  }
}

// 11. Student Portal - Request Return Book
async function requestReturn(loanId) {
  if (!confirm("Are you sure you want to request a return for this book? The Library Admin will verify and check it in.")) return;
  try {
    const result = await apiCall("library_loans.php?action=request_return", "POST", { loan_id: loanId });
    if (result.success) {
      showAlert(result.message, "success");
      loadStudentLoans();
    } else {
      showAlert(result.message || "Failed to submit return request", "danger");
    }
  } catch (error) {
    showAlert("Return request failed: " + error.message, "danger");
  }
}

// 12. Admin Portal - Library Pending Requests Queue
async function loadLibraryPendingRequests() {
  loadNotifications();
  try {
    const result = await apiCall("library_loans.php?filter=pending");
    if (result.success) {
      window.libraryPendingRequests = result.loans;
      filterLibraryRequests();
    }
  } catch (error) {
    showAlert("Failed to load library requests", "danger");
  }
}

// 12a. Admin Portal - Filter Library Requests
function filterLibraryRequests() {
  const loans = window.libraryPendingRequests || [];
  const query = (document.getElementById("libraryRequestSearchInput")?.value || "").toLowerCase().trim();
  const filter = document.getElementById("libraryRequestTypeFilter")?.value || "all";

  let filtered = loans;

  if (query) {
    filtered = filtered.filter(loan => 
      loan.student_name.toLowerCase().includes(query) ||
      loan.student_uid.toLowerCase().includes(query) ||
      loan.title.toLowerCase().includes(query) ||
      loan.isbn.toLowerCase().includes(query)
    );
  }

  if (filter !== "all") {
    filtered = filtered.filter(loan => loan.status === filter);
  }

  displayLibraryPendingRequests(filtered);
}

function displayLibraryPendingRequests(loans) {
  const h1 = document.querySelector("#requests .page-header h1");
  const p = document.querySelector("#requests .page-header p");
  if (h1) h1.textContent = "Library Requests";
  if (p) p.textContent = "Review and approve/reject book borrow, renewal, and return requests";

  const container = document.getElementById("requestsContainer");
  if (!container) return;
  container.innerHTML = "";

  // Update stats counts
  const totalPending = window.libraryPendingRequests ? window.libraryPendingRequests.length : loans.length;
  const pendingEl = document.getElementById("pendingCount");
  const approvedEl = document.getElementById("approvedCount");
  const totalEl = document.getElementById("totalRequestsCount");
  
  if (pendingEl) pendingEl.textContent = totalPending;
  if (approvedEl) approvedEl.textContent = "0";
  if (totalEl) totalEl.textContent = totalPending;

  if (loans.length === 0) {
    const hasActiveSearchOrFilter = (document.getElementById("libraryRequestSearchInput")?.value || "") || (document.getElementById("libraryRequestTypeFilter")?.value || "all") !== "all";
    container.innerHTML = hasActiveSearchOrFilter
      ? '<p style="text-align: center; color: #94a3b8; padding: 30px; font-weight: 500;">No pending requests match the criteria</p>'
      : '<p style="text-align: center; color: #999; padding: 30px;">No pending book borrow, renewal, or return requests</p>';
    return;
  }

  loans.forEach((loan) => {
    let actionLabel = "";
    let badgeColor = "";

    if (loan.status === 'Pending Borrow') {
      actionLabel = "Borrow Request";
      badgeColor = "#3b82f6"; // Blue
    } else if (loan.status === 'Pending Renewal') {
      actionLabel = "Renewal Request";
      badgeColor = "#f59e0b"; // Orange
    } else if (loan.status === 'Pending Return') {
      actionLabel = "Return Request";
      badgeColor = "#10b981"; // Green
    }

    const card = document.createElement("div");
    card.className = "request-card card";
    card.style = "margin-bottom: 20px; border-radius: 16px; border: 1px solid #e2e8f0; background: white; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); position: relative; transition: transform 0.2s;";
    
    // Calculate fine preview if overdue (only for returns)
    let fineHtml = "";
    if (loan.status === 'Pending Return') {
      const now = new Date();
      const due = new Date(loan.due_date);
      let fine = 0.00;
      if (now > due) {
        const diffTime = Math.abs(now - due);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        if (diffDays > 0) {
          fine = diffDays * 10.00;
        }
      }
      if (fine > 0) {
        fineHtml = `<div style="margin-top: 10px; color: #dc2626; font-weight: bold;">⚠️ Estimated Overdue Fine: ₹${fine.toFixed(2)}</div>`;
      }
    }

    let durationHtml = "";
    if (loan.status === 'Pending Borrow') {
      durationHtml = `<div style="color: #475569; font-size: 13px; margin-top: 8px;">⏳ Requested Duration: <strong>${loan.duration_days} Days</strong></div>`;
    }

    card.innerHTML = `
      <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
        <div>
          <span style="display: inline-block; padding: 4px 10px; border-radius: 20px; background: ${badgeColor}15; color: ${badgeColor}; font-size: 11px; font-weight: bold; text-transform: uppercase; margin-bottom: 10px;">${actionLabel}</span>
          <h3 style="margin: 0 0 5px 0; font-size: 18px; color: #0f172a; font-weight: bold;">${loan.student_name}</h3>
          <div style="color: #64748b; font-size: 13px; margin-bottom: 12px;">ID: ${loan.student_uid}</div>
          
          <div style="background: #f8fafc; border-radius: 8px; padding: 12px 16px; border-left: 4px solid ${badgeColor};">
            <div style="font-weight: 600; color: #1e293b; font-size: 14px;">📖 ${loan.title}</div>
            <div style="color: #64748b; font-size: 12px; margin-top: 2px;">ISBN: ${loan.isbn}</div>
          </div>
          ${durationHtml}
          ${fineHtml}
        </div>
        
        <div style="display: flex; gap: 10px; align-self: center;">
          <button class="btn btn-success" onclick="approveLibraryRequest(${loan.id}, '${loan.status}', ${loan.duration_days}, '${loan.student_name.replace(/'/g, "\\'")}')" style="padding: 10px 20px; font-size: 13px; font-weight: bold; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 5px; background: #10b981; border: none; color: white;">
            ✓ Approve
          </button>
          <button class="btn btn-danger" onclick="rejectLibraryRequest(${loan.id})" style="padding: 10px 20px; font-size: 13px; font-weight: bold; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 5px; background: #ef4444; border: none; color: white;">
            ✗ Reject
          </button>
        </div>
      </div>
    `;
    container.appendChild(card);
  });
}

function openLibraryLoanActionModal(title, text, showDuration, confirmCallback) {
  const modal = document.getElementById("libraryLoanActionModal");
  if (!modal) return;
  
  document.getElementById("libModalTitle").innerText = title;
  document.getElementById("libModalText").innerText = text;
  
  const durationField = document.getElementById("libModalDurationField");
  if (showDuration) {
    durationField.style.display = "block";
    document.getElementById("libModalDurationInput").value = showDuration;
  } else {
    durationField.style.display = "none";
  }
  
  const confirmBtn = document.getElementById("libModalConfirmBtn");
  if (title.toLowerCase().includes("reject")) {
    confirmBtn.className = "btn btn-danger";
    confirmBtn.innerText = "Reject Request";
  } else {
    confirmBtn.className = "btn btn-success";
    confirmBtn.innerText = "Approve Request";
  }
  
  confirmBtn.onclick = function() {
    let finalVal = null;
    if (showDuration) {
      const inputVal = parseInt(document.getElementById("libModalDurationInput").value);
      if (isNaN(inputVal) || inputVal <= 0) {
        showAlert("Please enter a valid positive number of days.", "danger");
        return;
      }
      finalVal = inputVal;
    }
    confirmCallback(finalVal);
    closeLibraryLoanActionModal();
  };
  
  modal.style.display = "flex";
}

function closeLibraryLoanActionModal() {
  const modal = document.getElementById("libraryLoanActionModal");
  if (modal) {
    modal.style.display = "none";
  }
}

async function approveLibraryRequest(loanId, status, originalDuration, studentName) {
  if (status === 'Pending Borrow') {
    openLibraryLoanActionModal(
      "📖 Approve Borrow Request",
      `Confirm/modify the borrow duration for student '${studentName}':`,
      originalDuration,
      async (duration) => {
        try {
          const result = await apiCall("library_loans.php?action=approve", "POST", { loan_id: loanId, duration_days: duration });
          if (result.success) {
            showAlert(result.message, "success");
            loadLibraryPendingRequests();
            if (typeof loadAdminLoans === 'function') {
              loadAdminLoans();
            }
          } else {
            showAlert(result.message || "Failed to approve request", "danger");
          }
        } catch (error) {
          showAlert("Approval failed: " + error.message, "danger");
        }
      }
    );
  } else {
    const actionText = status === 'Pending Return' ? 'approve return of this book' : 'approve a 7-day renewal';
    const actionTitle = status === 'Pending Return' ? '📖 Approve Return Request' : '📖 Approve Renewal Request';
    openLibraryLoanActionModal(
      actionTitle,
      `Are you sure you want to ${actionText} for student '${studentName}'?`,
      false,
      async () => {
        try {
          const result = await apiCall("library_loans.php?action=approve", "POST", { loan_id: loanId });
          if (result.success) {
            showAlert(result.message, "success");
            loadLibraryPendingRequests();
            if (typeof loadAdminLoans === 'function') {
              loadAdminLoans();
            }
          } else {
            showAlert(result.message || "Failed to approve request", "danger");
          }
        } catch (error) {
          showAlert("Approval failed: " + error.message, "danger");
        }
      }
    );
  }
}

async function rejectLibraryRequest(loanId) {
  openLibraryLoanActionModal(
    "✗ Reject Loan Request",
    "Are you sure you want to reject this library loan request?",
    false,
    async () => {
      try {
        const result = await apiCall("library_loans.php?action=reject", "POST", { loan_id: loanId });
        if (result.success) {
          showAlert(result.message, "success");
          loadLibraryPendingRequests();
          if (typeof loadAdminLoans === 'function') {
            loadAdminLoans();
          }
        } else {
          showAlert(result.message || "Failed to reject request", "danger");
        }
      } catch (error) {
        showAlert("Rejection failed: " + error.message, "danger");
      }
    }
  );
}

// 13. Admin Portal - Library History Queue
async function loadLibraryHistory() {
  try {
    const result = await apiCall("library_loans.php");
    if (result.success && result.loans) {
      const returnedLoans = result.loans.filter(l => l.status === 'Returned');
      displayLibraryHistory(returnedLoans);
    }
  } catch (error) {
    showAlert("Failed to load library history", "danger");
  }
}

function displayLibraryHistory(loans) {
  const container = document.getElementById("historyContainer");
  if (!container) return;
  container.innerHTML = "";

  const headerHtml = `
    <div class="page-header" style="margin-bottom: 25px;">
      <h1>Library History</h1>
      <p>Log of all returned books and resolved fines</p>
    </div>
  `;

  if (loans.length === 0) {
    container.innerHTML = headerHtml +
      '<div style="text-align: center; padding: 50px; background: #f9f9f9; border-radius: 8px;"><p style="color: #999; font-size: 16px;">📋 No return history available yet</p></div>';
    return;
  }

  const table = document.createElement("table");
  table.className = "history-table";
  table.innerHTML = `
    <thead>
      <tr>
        <th>Student Name</th>
        <th>Book Title</th>
        <th>Borrow Date</th>
        <th>Return Date</th>
        <th>Fine Paid</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      ${loans
        .map(
          (loan) => `
        <tr>
          <td>
            <div style="font-weight: bold; color: #1e293b;">${loan.student_name}</div>
            <div style="color: #64748b; font-size: 11px;">ID: ${loan.student_uid}</div>
          </td>
          <td>
            <div style="font-weight: bold; color: #1e293b;">${loan.title}</div>
            <div style="color: #64748b; font-size: 11px;">ISBN: ${loan.isbn}</div>
          </td>
          <td>${new Date(loan.borrow_date).toLocaleDateString()}</td>
          <td>${loan.return_date ? new Date(loan.return_date).toLocaleDateString() : "-"}</td>
          <td style="font-weight: bold; color: ${loan.fine_amount > 0 ? '#dc2626' : '#16a34a'};">₹${parseFloat(loan.fine_amount).toFixed(2)}</td>
          <td><span class="status-badge status-approved">${loan.status}</span></td>
        </tr>
      `,
        )
        .join("")}
    </tbody>
  `;
  container.appendChild(table);
  // Prepend the header
  const headerDiv = document.createElement("div");
  headerDiv.innerHTML = headerHtml;
  container.insertBefore(headerDiv.firstElementChild, table);
}

// === STUDENT COMMUNICATIONS PORTAL ===
let currentStudentClearance = null;
let currentStudentChatRole = null; // 'admin', 'library', or 'super_admin'
let currentStudentChatRequestId = null;
let studentChatInterval = null;

async function loadStudentChats() {
  const chatList = document.getElementById("studentAdminChatList");
  if (!chatList) return;

  try {
    const result = await apiCall("clearance_request.php");
    const clearance = (result && result.success) ? result.request : null;
    currentStudentClearance = clearance;

    // Identify student program/department admin display name
    const studentDept = (currentStudent && currentStudent.department) ? currentStudent.department : "Department";
    const deptDisplayName = `${studentDept} Admin`;

    if (!clearance) {
      // No active request
      chatList.innerHTML = `
        <div class="chat-student-item locked" onclick="selectStudentChatRecipient('admin', null, 'no_request')">
          <div class="chat-avatar" style="background: #475569;">🏛️</div>
          <div class="chat-info">
            <div class="chat-name">${deptDisplayName}</div>
            <div class="chat-last-msg">🔒 Clearance request not submitted</div>
          </div>
        </div>
        <div class="chat-student-item locked" onclick="selectStudentChatRecipient('library', null, 'no_request')">
          <div class="chat-avatar" style="background: #475569;">📚</div>
          <div class="chat-info">
            <div class="chat-name">Library Admin</div>
            <div class="chat-last-msg">🔒 Clearance request not submitted</div>
          </div>
        </div>
        <div class="chat-student-item locked" onclick="selectStudentChatRecipient('super_admin', null, 'no_request')">
          <div class="chat-avatar" style="background: #475569;">👑</div>
          <div class="chat-info">
            <div class="chat-name">Super Admin</div>
            <div class="chat-last-msg">🔒 Clearance request not submitted</div>
          </div>
        </div>
      `;
      
      const activeChatView = document.getElementById("studentActiveChatView");
      if (activeChatView) {
        activeChatView.innerHTML = `
          <div class="chat-placeholder">
            <div class="chat-icon">💬</div>
            <h3>Select an Administrator to start chatting</h3>
            <p>Discuss your clearance status, questions, or appeal notes.</p>
          </div>
        `;
      }
      return;
    }

    currentStudentChatRequestId = clearance.id;

    const unread = clearance.unread_counts || { admin: 0, library: 0, super_admin: 0 };
    const adminBadge = unread.admin > 0 ? `<span class="unread-badge" style="background: #ef4444; color: white; border-radius: 12px; padding: 2px 8px; font-size: 11px; font-weight: bold; margin-left: auto; display: flex; align-items: center; justify-content: center; min-width: 18px; height: 18px;">${unread.admin}</span>` : '';
    const libraryBadge = unread.library > 0 ? `<span class="unread-badge" style="background: #ef4444; color: white; border-radius: 12px; padding: 2px 8px; font-size: 11px; font-weight: bold; margin-left: auto; display: flex; align-items: center; justify-content: center; min-width: 18px; height: 18px;">${unread.library}</span>` : '';
    const superBadge = unread.super_admin > 0 ? `<span class="unread-badge" style="background: #ef4444; color: white; border-radius: 12px; padding: 2px 8px; font-size: 11px; font-weight: bold; margin-left: auto; display: flex; align-items: center; justify-content: center; min-width: 18px; height: 18px;">${unread.super_admin}</span>` : '';

    chatList.innerHTML = `
      <div class="chat-student-item active-room" id="recipient_admin" onclick="selectStudentChatRecipient('admin', ${clearance.id}, true)" style="display: flex; align-items: center; width: 100%;">
        <div class="chat-avatar">🏛️</div>
        <div class="chat-info" style="flex: 1;">
          <div class="chat-name">${deptDisplayName}</div>
          <div class="chat-last-msg">💬 Active Chat Room</div>
        </div>
        ${adminBadge}
      </div>
      <div class="chat-student-item active-room" id="recipient_library" onclick="selectStudentChatRecipient('library', ${clearance.id}, true)" style="display: flex; align-items: center; width: 100%;">
        <div class="chat-avatar" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;">📚</div>
        <div class="chat-info" style="flex: 1;">
          <div class="chat-name">Library Admin</div>
          <div class="chat-last-msg">💬 Active Chat Room</div>
        </div>
        ${libraryBadge}
      </div>
      <div class="chat-student-item active-room" id="recipient_super_admin" onclick="selectStudentChatRecipient('super_admin', ${clearance.id}, true)" style="display: flex; align-items: center; width: 100%;">
        <div class="chat-avatar">👑</div>
        <div class="chat-info" style="flex: 1;">
          <div class="chat-name">Super Admin</div>
          <div class="chat-last-msg">💬 Active Chat Room</div>
        </div>
        ${superBadge}
      </div>
    `;

    // Auto-select Department Admin by default on first load
    if (!currentStudentChatRole) {
      selectStudentChatRecipient('admin', clearance.id, true);
    } else {
      const activeEl = document.getElementById(`recipient_${currentStudentChatRole}`);
      if (activeEl) {
        activeEl.classList.add("active");
        const badge = activeEl.querySelector(".unread-badge");
        if (badge) badge.remove();
      }
    }

  } catch (error) {
    console.error("loadStudentChats error:", error);
    chatList.innerHTML = '<p style="text-align: center; color: #ef4444; padding: 20px;">⚠️ Error loading recipients</p>';
  }
}

function selectStudentChatRecipient(role, requestId, eligibility) {
  document.querySelectorAll(".chat-student-item").forEach(item => {
    item.classList.remove("active");
  });

  const selectedEl = document.getElementById(`recipient_${role}`);
  if (selectedEl) {
    selectedEl.classList.add("active");
    const badge = selectedEl.querySelector(".unread-badge");
    if (badge) badge.remove();
  }

  currentStudentChatRole = role;
  currentStudentChatRequestId = requestId;

  if (studentChatInterval) {
    clearInterval(studentChatInterval);
    studentChatInterval = null;
  }

  const activeChatView = document.getElementById("studentActiveChatView");
  if (!activeChatView) return;

  const studentDept = (currentStudent && currentStudent.department) ? currentStudent.department : "Department";
  let displayName = "Administrator";
  if (role === 'admin') displayName = `${studentDept} Admin`;
  else if (role === 'library') displayName = "Library Admin";
  else if (role === 'super_admin') displayName = "Super Admin";

  if (eligibility === 'no_request') {
    activeChatView.innerHTML = `
      <div class="chat-window" style="display: flex; flex-direction: column; height: 100%;">
        <div class="chat-header">
          <h4>${displayName}</h4>
          <span style="font-size: 11px; opacity: 0.8;">Role: ${role === 'admin' ? 'Departmental Admin' : (role === 'library' ? 'Library Admin' : 'Super Admin')}</span>
        </div>
        <div class="chat-messages" style="flex: 1; display: flex; align-items: center; justify-content: center; background: rgba(12,12,14,0.5);">
          <div style="text-align: center; color: #94a3b8; padding: 40px;">
            <div style="font-size: 40px; margin-bottom: 15px;">🔒</div>
            <h3>Chat Unavailable</h3>
            <p>You must submit a clearance request first to start communicating.</p>
          </div>
        </div>
      </div>
    `;
    return;
  }

  activeChatView.innerHTML = `
    <div class="chat-window" style="display: flex; flex-direction: column; height: 100%;">
      <div class="chat-header">
        <h4>${displayName}</h4>
        <span style="font-size: 11px; opacity: 0.8;">Connected to Clearance Request ID: #${requestId}</span>
      </div>
      <div class="chat-messages" id="studentChatMessages" style="flex: 1; overflow-y: auto; background: rgba(12,12,14,0.5);">
        <div style="text-align: center; color: #94a3b8; padding: 40px;">Loading conversation...</div>
      </div>
      <div class="chat-input-area">
        <input type="text" id="studentChatInput" style="flex: 1; padding: 12px; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; background: rgba(255,255,255,0.05); color: white; font-size: 14px;" placeholder="Type a message to ${displayName}..." onkeypress="if(event.key === 'Enter') sendStudentMessage()">
        <button class="btn btn-primary" onclick="sendStudentMessage()" style="padding: 10px 20px; font-weight: 600;">Send</button>
      </div>
    </div>
  `;
  loadStudentChatMessages(false);
  studentChatInterval = setInterval(() => loadStudentChatMessages(false), 5000);
}

async function loadStudentChatMessages(isReadOnly = false) {
  if (!currentStudentChatRequestId || !currentStudentChatRole) return;

  try {
    const data = await apiCall(`get_messages.php?request_id=${currentStudentChatRequestId}&recipient_role=${currentStudentChatRole}`);
    const container = document.getElementById("studentChatMessages");
    if (!container) return;

    if (data.success && data.messages) {
      const filteredMessages = data.messages.filter(msg => {
        if (currentStudentChatRole === 'admin') {
          // CSE/Department Admin: sender_role = 'admin' (non-Library) OR student sends to 'admin'
          if (msg.sender_role === 'admin' && msg.sender_dept !== 'Library') return true;
          if (msg.sender_role === 'student' && (msg.recipient_role === 'admin' || msg.recipient_role === null)) return true;
          return false;
        } else if (currentStudentChatRole === 'library') {
          // Library Admin: sender_role = 'admin' (Library dept) OR student sends to 'library'
          if (msg.sender_role === 'admin' && msg.sender_dept === 'Library') return true;
          if (msg.sender_role === 'student' && msg.recipient_role === 'library') return true;
          return false;
        } else {
          // Super Admin: sender_role = 'super_admin' OR student sends to 'super_admin'
          if (msg.sender_role === 'super_admin') return true;
          if (msg.sender_role === 'student' && msg.recipient_role === 'super_admin') return true;
          return false;
        }
      });

      if (filteredMessages.length === 0) {
        container.innerHTML = '<div style="text-align: center; color: #94a3b8; padding: 40px; font-size: 13px;">No messages exchanged yet. Send a note to start.</div>';
        return;
      }

      const html = filteredMessages.map(msg => {
        const isSentByMe = (msg.sender_role === 'student');
        const bubbleClass = isSentByMe ? 'sent' : 'received';
        const senderLabel = isSentByMe ? 'Me' : msg.sender_name;
        
        return `
          <div class="msg ${bubbleClass}">
            <div style="font-size: 10px; opacity: 0.8; margin-bottom: 4px; font-weight: bold;">${senderLabel}</div>
            <div>${msg.message}</div>
            <div style="font-size: 9px; opacity: 0.6; text-align: right; margin-top: 4px;">${new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
          </div>
        `;
      }).join("");

      const shouldScroll = container.scrollTop + container.clientHeight >= container.scrollHeight - 20 || container.querySelector("div").textContent.includes("Loading");
      container.innerHTML = html;
      if (shouldScroll) {
        container.scrollTop = container.scrollHeight;
      }
    }
  } catch (error) {
    console.error("loadStudentChatMessages error:", error);
  }
}

async function sendStudentMessage() {
  if (!currentStudentChatRequestId || !currentStudentChatRole) return;
  const input = document.getElementById("studentChatInput");
  if (!input) return;

  const msg = input.value.trim();
  if (!msg) return;

  try {
    const result = await apiCall("send_message.php", "POST", {
      request_id: currentStudentChatRequestId,
      message: msg,
      recipient_role: currentStudentChatRole
    });

    if (result.success) {
      input.value = "";
      loadStudentChatMessages(false);
    }
  } catch (error) {
    console.error("sendStudentMessage error:", error);
  }
}

