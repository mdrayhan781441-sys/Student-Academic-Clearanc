<?php
require_once '../config/session.php';
requireRole('student');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Online Clearance Cloud</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/student_dashboard_premium.css?v=<?php echo time(); ?>">
</head>

<body data-page="student-dashboard">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-top">
                <div class="logo-circle">🎓</div>
                <div style="display: flex; flex-direction: column;">
                    <span class="role-badge">STUDENT PORTAL</span>
                    <span style="color: #64b5f6; font-size: 11px; font-weight: bold; margin-top: 5px; text-transform: uppercase; letter-spacing: 1px;">CLEARANCE CENTER</span>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li class="sidebar-item">
                    <a class="sidebar-link active" href="#dashboard" onclick="switchTab(event, 'dashboard')">
                        <span>📊</span> Dashboard
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="#payment" onclick="switchTab(event, 'payment')">
                        <span>💳</span> Payment
                    </a>
                </li>

                <li class="sidebar-item">
                    <a class="sidebar-link" href="#certificate" onclick="switchTab(event, 'certificate')">
                        <span>📄</span> Certificate
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="#library" onclick="switchTab(event, 'library'); if(typeof loadLibraryBooks === 'function') { loadLibraryBooks(); loadStudentLoans(); }">
                        <span>📚</span> Library
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="#profile" onclick="switchTab(event, 'profile')">
                        <span>👤</span> Profile
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="#announcements" onclick="switchTab(event, 'announcements'); if(typeof loadAnnouncements === 'function') loadAnnouncements();">
                        <span>📢</span> Announcements
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="#messages" onclick="switchTab(event, 'messages'); if(typeof loadStudentChats === 'function') loadStudentChats();">
                        <span>💬</span> Messages
                    </a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <a class="sidebar-link logout-btn" href="javascript:void(0)" onclick="handleLogout();" style="cursor: pointer; display: flex; align-items: center; width: 100%;">
                    <span>🚪</span> Sign Out
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <header class="top-bar">
                <div class="page-title">
                    <span style="color: #64748b; font-weight: 500;">Hello,</span> Student
                </div>
                <div class="user-actions">
                    <div class="notification-wrapper">
                        <div class="notification-icon" onclick="toggleNotifications()" title="Notifications">
                            🔔<span id="notifBadge" class="badge" style="display:none;">0</span>
                        </div>
                        <div id="notifDropdown" class="notif-dropdown">
                            <div class="notif-header">Personal Notifications</div>
                            <div id="notifList" class="notif-list">
                                <div class="notif-empty">No new notifications</div>
                            </div>
                        </div>
                    </div>
                    <div class="user-avatar" title="Student Profile">👤</div>
            </header>

            <!-- Announcement Ticker -->
            <div class="announcement-ticker-container">
                <div class="ticker-badge">📢 Announcement</div>
                <div class="ticker-wrap">
                    <div id="announcementTickerList" class="ticker-items">
                        Loading latest announcements...
                    </div>
                </div>
            </div>

            <!-- Dashboard Tab -->
            <section id="dashboard" class="tab-content" style="display: block;">
                <!-- Action Notifications Area -->
                <div id="actionNotificationsArea"></div>

                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="profile-avatar-large">👤</div>
                    <div class="profile-info-main">
                        <h1>Welcome back, <span id="welcomeStudentName">Loading...</span></h1>
                        <p>Student ID: <span id="studentId">Loading...</span></p>
                    </div>
                </div>

                <div class="dashboard-grid-top">
                    <!-- Left Column: Progress & Profile Summary -->
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <!-- Progress Card -->
                        <div class="card progress-card">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                                <span style="font-weight: bold;">Clearance Completion Progress</span>
                                <span style="font-weight: bold;" id="progressPercentage">0%</span>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill" id="progressBar" style="width: 0%;">0%</div>
                            </div>
                        </div>

                        <!-- Personal Information -->
                        <div class="card">
                            <div class="card-header">
                                <h2>🎓 Academic Profile</h2>
                            </div>
                            <div class="card-body">
                                <div class="student-info">
                                    <div class="info-item">
                                        <div class="info-label">Full Name</div>
                                        <div class="info-value" id="studentName">Loading...</div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Department</div>
                                        <div class="info-value" id="studentDepartment">Loading...</div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">CGPA</div>
                                        <div class="info-value" id="studentCGPA">Loading...</div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Clearance Status</div>
                                        <div id="clearanceStatus" style="margin-top: 10px;">
                                            <span id="clearanceStatusBadge" class="status-badge status-pending">No Request Yet</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: QR Access & Announcements -->
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <!-- QR Card -->
                        <div class="card qr-card" style="display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                            <img id="qrCodeImg" src="" alt="Access QR Code" style="width: 130px; height: 130px; background: #f8fafc; padding: 10px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 10px;">
                            <div class="qr-label">Academic Access QR</div>
                            <div class="qr-subtext">Scan at administrative offices for quick verification</div>
                        </div>

                        <!-- Departmental Announcements -->
                        <div class="card">
                            <div class="card-header">
                                <h2>📢 Department Announcements</h2>
                            </div>
                            <div class="card-body" style="max-height: 250px; overflow-y: auto;">
                                <div id="studentAnnouncementsList" class="ann-list-student">
                                    <p style="text-align: center; color: #94a3b8; padding: 20px; font-size: 13px;">No announcements broadcasted yet</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid-bottom" style="margin-top: 25px;">
                    <!-- Left Column: Dues & Timeline -->
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <!-- Due Breakdown -->
                        <div class="card">
                            <div class="card-header">
                                <h2>💰 Dues & Liabilities Breakdown</h2>
                            </div>
                            <div class="card-body">
                                <div class="due-breakdown">
                                    <div class="due-item">
                                        <div class="due-title">📚 Library Clearance</div>
                                        <div class="due-stats">
                                            <div class="due-stat">
                                                <span class="stat-label">Amount Due</span>
                                                <span class="stat-value" id="libraryDue">₹0.00</span>
                                            </div>
                                            <div class="due-stat">
                                                <span class="stat-label">Amount Paid</span>
                                                <span class="stat-value" id="libraryPaid">₹0.00</span>
                                            </div>
                                        </div>
                                        <div class="due-status-wrapper" style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                                            <span class="status-badge" id="libraryStatus">Due</span>
                                            <button class="btn btn-secondary btn-small" onclick="preparePayment('library')" style="padding: 6px 12px; font-size: 12px; border-radius: 12px;">Pay Dues</button>
                                        </div>
                                    </div>

                                    <div class="due-item">
                                        <div class="due-title">🏠 Hostel Clearance</div>
                                        <div class="due-stats">
                                            <div class="due-stat">
                                                <span class="stat-label">Amount Due</span>
                                                <span class="stat-value" id="hostelDue">₹0.00</span>
                                            </div>
                                            <div class="due-stat">
                                                <span class="stat-label">Amount Paid</span>
                                                <span class="stat-value" id="hostelPaid">₹0.00</span>
                                            </div>
                                        </div>
                                        <div class="due-status-wrapper" style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                                            <span class="status-badge" id="hostelStatus">Due</span>
                                            <button class="btn btn-secondary btn-small" onclick="preparePayment('hostel')" style="padding: 6px 12px; font-size: 12px; border-radius: 12px;">Pay Dues</button>
                                        </div>
                                    </div>

                                    <div class="due-item">
                                        <div class="due-title">🎓 Tuition Fee</div>
                                        <div class="due-stats">
                                            <div class="due-stat">
                                                <span class="stat-label">Amount Due</span>
                                                <span class="stat-value" id="tuitionDue">₹0.00</span>
                                            </div>
                                            <div class="due-stat">
                                                <span class="stat-label">Amount Paid</span>
                                                <span class="stat-value" id="tuitionPaid">₹0.00</span>
                                            </div>
                                        </div>
                                        <div class="due-status-wrapper" style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                                            <span class="status-badge" id="tuitionStatus">Due</span>
                                            <button class="btn btn-secondary btn-small" onclick="preparePayment('tuition')" style="padding: 6px 12px; font-size: 12px; border-radius: 12px;">Pay Dues</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Timeline Tracker Card -->
                        <div class="card" id="approvalTimelineCard" style="display: none;">
                            <div class="card-header">
                                <h2>📋 Clearance Approval Tracker</h2>
                            </div>
                            <div class="card-body">
                                <div class="timeline-horizontal">
                                    <div class="timeline-step" id="step-submitted">
                                        <div class="step-icon">📤</div>
                                        <span class="step-label">Submitted</span>
                                    </div>
                                    <div class="timeline-connector"></div>
                                    <div class="timeline-step" id="step-admin">
                                        <div class="step-icon">🏛️</div>
                                        <span class="step-label">Admin</span>
                                    </div>
                                    <div class="timeline-connector"></div>
                                    <div class="timeline-step" id="step-super">
                                        <div class="step-icon">👑</div>
                                        <span class="step-label">Super Admin</span>
                                    </div>
                                    <div class="timeline-connector"></div>
                                    <div class="timeline-step" id="step-completed">
                                        <div class="step-icon">🎉</div>
                                        <span class="step-label">Completed</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity Timeline Card -->
                        <div class="card">
                            <div class="card-header">
                                <h2>📜 Recent Activities</h2>
                            </div>
                            <div class="card-body">
                                <div id="activityTimeline" class="activity-timeline">
                                    <p style="text-align: center; color: #94a3b8; padding: 20px;">No recent activity</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Summary, Forms & Docs -->
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <!-- Payment Summary -->
                        <div class="card">
                            <div class="card-header">
                                <h2>💳 Financial Summary</h2>
                            </div>
                            <div class="card-body">
                                <div class="summary-section" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                                    <div class="summary-item">
                                        <div class="summary-label">Total Dues</div>
                                        <div class="summary-value" id="totalDue" style="font-size: 16px;">₹0.00</div>
                                    </div>
                                    <div class="summary-item">
                                        <div class="summary-label">Total Paid</div>
                                        <div class="summary-value" id="totalPaid" style="font-size: 16px; color: var(--p-success);">₹0.00</div>
                                    </div>
                                    <div class="summary-item highlight" style="background: linear-gradient(135deg, var(--p-primary), var(--p-primary-dark)); color: white;">
                                        <div class="summary-label" style="color: rgba(255,255,255,0.8);">Balance</div>
                                        <div class="summary-value" id="remainingBalance" style="font-size: 16px; color: white;">₹0.00</div>
                                    </div>
                                </div>
                            </div>
                        </div>


                    </div>
                </div>

                <!-- Rejection Reason -->
                <div id="rejectionReason" style="display: none; background: #fff3e5; padding: 15px; border-radius: 12px; color: #856404; margin-top: 20px; border-left: 4px solid #ef4444;">
                </div>
            </section>

            <!-- Payment Tab -->
            <section id="payment" class="tab-content" style="display: none;">
                <div class="page-header">
                    <h1>Make Payment</h1>
                    <p>Pay your dues and proceed towards clearance</p>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>Payment Details</h2>
                    </div>
                    <div class="card-body">
                        <form id="paymentForm" onsubmit="event.preventDefault(); processPayment();">
                            <div class="form-group">
                                <label for="paymentType">Pay For:</label>
                                <select id="paymentType" required onchange="updatePaymentAmount()">
                                    <option value="all">All Dues (Combined)</option>
                                    <option value="library">Library Clearance Only</option>
                                    <option value="hostel">Hostel Clearance Only</option>
                                    <option value="tuition">Tuition Fee Only</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="paymentAmount">Amount (₹):</label>
                                <input type="number" id="paymentAmount" step="0.01" min="0" required placeholder="Enter amount to pay">
                            </div>

                            <div class="form-group">
                                <label for="paymentPhone">Payment Phone Number (Bangladesh Gateway):</label>
                                <input type="tel" id="paymentPhone" placeholder="e.g. 017XXXXXXXX" required>
                            </div>

                            <button type="submit" class="btn btn-primary btn-full">Process Payment</button>
                        </form>
                    </div>
                </div>

                <!-- Payment History Section -->
                <div class="card" style="margin-top: 25px;">
                    <div class="card-header">
                        <h2>Payment History</h2>
                    </div>
                    <div class="card-body">
                        <div id="paymentHistoryContainer" style="overflow-x: auto;">
                            <table class="history-table" style="width:100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Payment Type</th>
                                        <th>Amount Paid</th>
                                        <th>Library Paid</th>
                                        <th>Hostel Paid</th>
                                        <th>Tuition Paid</th>
                                        <th>Total Paid</th>
                                    </tr>
                                </thead>
                                <tbody id="paymentHistoryTable">
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 20px; color: #999;">No payment history yet</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>



            <!-- Certificate Tab -->
            <section id="certificate" class="tab-content" style="display: none;">
                <div class="page-header">
                    <h1>Certificate</h1>
                    <p>Download your academic clearance certificate</p>
                </div>

                <div class="card" style="text-align: center; padding: 50px 30px;">
                    <div class="card-body">
                        <div class="cert-icon-large">📜</div>
                        <h2 style="margin-bottom: 15px;">Academic Clearance Certificate</h2>
                        <p style="color: var(--p-text-muted); max-width: 600px; margin: 0 auto 30px auto; font-size: 15px; line-height: 1.6;">
                            This official certificate serves as institutional clearance, confirming that you have cleared all academic, library, hostel, and tuition fee dues.
                        </p>
                        
                        <button id="generateCertBtn" class="btn btn-download btn-primary" onclick="downloadCertificate()" style="padding: 18px 45px; font-size: 16px;" disabled>
                            Please wait until Super Admin approval
                        </button>

                        <div style="background: #fff3cd; padding: 20px; border-radius: 12px; margin-top: 30px; border-left: 4px solid #ffc107; text-align: left;">
                            <strong>⚠️ Certificate Eligibility:</strong>
                            <p style="margin: 8px 0 0 0; font-size: 14px; color: #856404; line-height: 1.5;">
                                Your certificate will become downloadable instantly as soon as your clearance request is approved by both your Departmental Administrator and the Super Administrator.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Library Tab -->
            <section id="library" class="tab-content" style="display: none;">
                <div class="page-header">
                    <h1>📚 Library Book Portal</h1>
                    <p>Search library catalog and borrow books online</p>
                </div>

                <!-- Books Search and Catalog -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                        <h2>Library Book Catalog</h2>
                        <div style="position: relative; width: 300px;">
                            <input type="text" id="bookSearchInput" placeholder="🔍 Search by Title, Author, or ISBN..." oninput="searchLibraryBooks()" style="width: 100%; padding: 8px 12px 8px 32px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: white; color: black;">
                            <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 13px;">🔍</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div style="overflow-x: auto;">
                            <table class="history-table" style="width:100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th>Cover</th>
                                        <th>Book Title</th>
                                        <th>Author</th>
                                        <th>ISBN</th>
                                        <th>Available Copies</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="libraryBooksTable">
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 20px; color: #999;">Loading books...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- My Borrowed Books -->
                <div class="card" style="margin-top: 25px;">
                    <div class="card-header">
                        <h2>My Borrowed Books</h2>
                    </div>
                    <div class="card-body">
                        <div style="overflow-x: auto;">
                            <table class="history-table" style="width:100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th>Book Title</th>
                                        <th>Author</th>
                                        <th>ISBN</th>
                                        <th>Borrow Date</th>
                                        <th>Due Date</th>
                                        <th>Return Date</th>
                                        <th>Fine Balance</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="myLoansTable">
                                    <tr>
                                        <td colspan="9" style="text-align: center; padding: 20px; color: #999;">No borrow records found</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Profile Tab -->
            <section id="profile" class="tab-content" style="display: none;">
                <div class="acad-header">
                    <div>
                        <h1>👤 Official Student Profile</h1>
                        <p style="color: #64748b; font-weight: 500;">Verify institutional records and manage your contact information</p>
                    </div>
                </div>

                <div class="profile-card-acad">
                    <div class="header-strip" style="background: var(--p-primary-dark);"></div>
                    <div class="body">
                        <div class="acad-avatar" id="profileAvatarChar">🎓</div>
                        <div style="flex: 1;">
                            <table class="acad-info-table">
                                <tr>
                                    <td class="label">Full Name</td>
                                    <td class="value"><span id="profName">Loading...</span></td>
                                </tr>
                                <tr>
                                    <td class="label">Student ID</td>
                                    <td class="value"><span id="profId">Loading...</span></td>
                                </tr>
                                <tr>
                                    <td class="label">Academic Program</td>
                                    <td class="value"><span id="profDept">Loading...</span></td>
                                </tr>
                                <tr>
                                    <td class="label">Cumulative GPA</td>
                                    <td class="value"><span id="profCGPA">Loading...</span></td>
                                </tr>
                                <tr>
                                    <td class="label">Email Address</td>
                                    <td class="value">
                                        <span class="view-mode" id="profEmail">Loading...</span>
                                        <input type="email" class="edit-mode" id="editEmail" style="display: none; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; width: 80%; font-size: 14px;">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label">Phone Number</td>
                                    <td class="value">
                                        <span class="view-mode" id="profPhone">Loading...</span>
                                        <input type="text" class="edit-mode" id="editPhone" style="display: none; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; width: 80%; font-size: 14px;">
                                    </td>
                                </tr>
                            </table>
                            <div style="margin-top: 25px; display: flex; gap: 10px;">
                                <button class="btn btn-secondary" id="editProfileBtn" onclick="toggleEditProfile(true)" style="padding: 10px 20px; background: #64748b; color: white;">Edit Profile</button>
                                <button class="btn btn-secondary" id="cancelEditBtn" onclick="toggleEditProfile(false)" style="display: none; padding: 10px 20px; background: #e2e8f0; color: #1e293b;">Cancel</button>
                                <button class="btn btn-primary" id="saveProfileBtn" onclick="saveProfile()" style="display: none; padding: 10px 20px;">Save Changes</button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Announcements Tab -->
            <section id="announcements" class="tab-content" style="display: none;">
                <div class="acad-header" style="margin-bottom: 20px;">
                    <div>
                        <h1>📢 Campus Announcements</h1>
                        <p style="color: #64748b; font-weight: 500;">Stay updated with the latest instructions and clearance notices from university officials</p>
                    </div>
                </div>

                <div class="card" style="border-radius: 12px; border: 1px solid #e2e8f0; background: white; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); margin-top: 25px;">
                    <div class="card-header" style="padding: 20px; border-bottom: 1px solid #e2e8f0;">
                        <h2 style="margin: 0; font-size: 18px; color: #1e293b;">Latest Bulletins</h2>
                    </div>
                    <div class="card-body" style="padding: 20px;">
                        <div id="studentAnnouncementsTabList" class="ann-list" style="display: flex; flex-direction: column; gap: 15px;">
                            <p style="text-align: center; color: #94a3b8; padding: 40px;">No announcements broadcasted yet</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Messages Tab -->
            <section id="messages" class="tab-content" style="display: none;">
                <div class="card chat-card">
                    <div class="card-header">
                        <h2>Admin Communications</h2>
                    </div>
                    <div class="card-body">
                        <div class="chat-container">
                            <div class="student-chat-list" id="studentAdminChatList">
                                <p style="text-align: center; color: #94a3b8; padding: 20px;">Loading recipients...</p>
                            </div>
                            <div class="chat-view" id="studentActiveChatView">
                                <div class="chat-placeholder">
                                    <div class="chat-icon">💬</div>
                                    <h3>Select an Administrator to start chatting</h3>
                                    <p>Discuss your clearance status, questions, or appeal notes.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Appeal Modal -->
    <div id="appealModal" class="modal">
        <div class="modal-content" style="max-width: 500px; border-radius: 20px;">
            <div class="modal-header">
                <h2>📤 Appeal / Resubmit Request</h2>
                <button class="modal-close" onclick="closeAppealModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="appealMessage" style="display: block; font-weight: bold; margin-bottom: 8px; font-size: 14px;">Appeal Explanation / Note:</label>
                    <textarea id="appealMessage" placeholder="Explain your appeal or provide info about newly uploaded documents..." style="width: 100%; height: 120px; padding: 10px; border: 1px solid #ddd; border-radius: 12px; resize: vertical; font-size: 14px;" required></textarea>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="appealDocument" style="display: block; font-weight: bold; margin-bottom: 8px; font-size: 14px;">Upload Supporting Document (Optional):</label>
                    <input type="file" id="appealDocument" accept=".pdf,.png,.jpg,.jpeg" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 12px; font-size: 14px; background: white;">
                </div>
                <p style="font-size: 12px; color: var(--p-text-muted); line-height: 1.4;">
                    * Submitting an appeal will reset your status to Pending and notify the department administrator to review your documents again.
                </p>
            </div>
            <div class="modal-footer" style="display:flex; justify-content: flex-end; gap: 10px; padding-top: 15px;">
                <button class="btn btn-secondary" onclick="closeAppealModal()" style="padding: 10px 20px; background: #e2e8f0; color: #1e293b;">Cancel</button>
                <button class="btn btn-primary" onclick="submitAppeal()" style="padding: 10px 20px;">Send Appeal</button>
            </div>
        </div>
    </div>

    <!-- Request Book Borrow Modal -->
    <div id="borrowModal" class="modal">
        <div class="modal-content" style="max-width: 400px; border-radius: 20px;">
            <div class="modal-header">
                <h2>📖 Request Book Borrow</h2>
                <button class="modal-close" onclick="closeBorrowModal()">&times;</button>
            </div>
            <div class="modal-body" style="padding-top: 15px;">
                <input type="hidden" id="borrowBookId">
                <div style="font-weight: 600; font-size: 15px; color: #1e293b; margin-bottom: 15px;">Book: <span id="borrowBookTitle" style="color: var(--p-primary);"></span></div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="borrowReturnDate" style="display: block; font-weight: bold; margin-bottom: 8px; font-size: 14px;">Expected Return Date & Time:</label>
                    <input type="datetime-local" id="borrowReturnDate" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 14px; background: white; cursor: pointer; color: black;" required>
                    <div style="font-size: 12px; color: #64748b; margin-top: 6px; font-weight: 500;" id="calculatedDurationText">Selected duration: 14 Days</div>
                </div>
            </div>
            <div class="modal-footer" style="display:flex; justify-content: flex-end; gap: 10px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                <button class="btn btn-secondary" onclick="closeBorrowModal()" style="padding: 10px 20px; background: #e2e8f0; color: #1e293b;">Cancel</button>
                <button class="btn btn-primary" onclick="submitBorrowRequest()" style="padding: 10px 20px;">Submit Request</button>
            </div>
        </div>
    </div>

    <!-- Chat Modal -->
    <div id="chatModal" class="modal">
        <div class="modal-content" style="max-width: 500px; border-radius: 20px;">
            <div class="modal-header">
                <h2>💬 Chat with Administrator</h2>
                <button class="modal-close" onclick="closeChatModal()">&times;</button>
            </div>
            <div class="modal-body" style="height: 350px; display: flex; flex-direction: column; gap: 15px; padding-top: 15px;">
                <div id="chatMessages" class="chat-messages" style="flex: 1; overflow-y: auto; background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 10px;">
                    <div style="text-align: center; color: #94a3b8; padding: 40px; font-size: 13px;">Loading conversation...</div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <input type="text" id="chatInput" style="flex: 1; padding: 12px; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 14px;" placeholder="Write message..." onkeypress="if(event.key==='Enter')sendMessage()">
                    <button class="btn btn-primary" onclick="sendMessage()" style="padding: 10px 20px;">Send</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/script.js?v=<?php echo time(); ?>"></script>
    <script>
    // Load student dashboard data on DOM ready
    document.addEventListener('DOMContentLoaded', loadStudentDashboard);

    // Tab switching
    function switchTab(event, tabName) {
        event.preventDefault();

        // Clear student chat interval if switching away from messages
        if (tabName !== 'messages' && typeof studentChatInterval !== 'undefined' && studentChatInterval) {
            clearInterval(studentChatInterval);
            studentChatInterval = null;
        }

        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.style.display = 'none';
        });

        // Remove active class from all links
        document.querySelectorAll('.sidebar-link').forEach(link => {
            link.classList.remove('active');
        });

        // Show selected tab
        const tabEl = document.getElementById(tabName);
        if (tabEl) {
            tabEl.style.display = 'block';
            
            // Trigger loadStudentChats if entering messages tab
            if (tabName === 'messages' && typeof loadStudentChats === 'function') {
                loadStudentChats();
            }
        }

        // Highlight caller link
        event.currentTarget.classList.add('active');
    }
    </script>
</body>

</html>