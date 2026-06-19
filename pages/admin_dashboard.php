<?php
require_once '../config/session.php';
requireRole('admin');
$is_library = (isset($_SESSION['department']) && $_SESSION['department'] === 'Library');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Online Clearance Cloud</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/admin_premium.css?v=<?php echo time(); ?>">
</head>

<body data-page="admin-dashboard">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-top">
                <div class="logo-circle">🔐</div>
                <div style="display: flex; flex-direction: column;">
                    <span class="role-badge">ADMIN PORTAL</span>
                    <span style="color: #64b5f6; font-size: 11px; font-weight: bold; margin-top: 5px; text-transform: uppercase; letter-spacing: 1px;"><?php echo $_SESSION['department']; ?></span>
                </div>
            </div>

            <ul class="sidebar-menu">
                <?php if ($is_library): ?>
                    <!-- Library Admin Menu (Only Library-related features) -->
                    <li class="sidebar-item">
                        <a class="sidebar-link active" href="#library-admin" onclick="switchTab(event, 'library-admin'); if(typeof loadAdminLibraryCatalog === 'function') { loadAdminLibraryCatalog(); loadAdminLoans(); }">
                            <span>📚</span> Library Admin
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="#requests" onclick="switchTab(event, 'requests')">
                            <span>📋</span> Requests
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="#profile" onclick="switchTab(event, 'profile')">
                            <span>👤</span> Profile
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="#messages" onclick="switchTab(event, 'messages')">
                            <span>💬</span> Messages
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="#history" onclick="switchTab(event, 'history')">
                            <span>📜</span> History
                        </a>
                    </li>
                <?php else: ?>
                    <!-- General Department Admin Menu -->
                    <li class="sidebar-item">
                        <a class="sidebar-link active" href="#requests" onclick="switchTab(event, 'requests')">
                            <span>📋</span> Requests
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="#students" onclick="switchTab(event, 'students')">
                            <span>👥</span> Students
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="#profile" onclick="switchTab(event, 'profile')">
                            <span>👤</span> Profile
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="#announcements" onclick="switchTab(event, 'announcements')">
                            <span>📢</span> Announcements
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="#reports" onclick="switchTab(event, 'reports')">
                            <span>📊</span> Reports
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="#messages" onclick="switchTab(event, 'messages')">
                            <span>💬</span> Messages
                        </a>
                    </li>
                    <?php if ($_SESSION['role'] === 'super_admin'): ?>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="#library-admin" onclick="switchTab(event, 'library-admin'); if(typeof loadAdminLibraryCatalog === 'function') { loadAdminLibraryCatalog(); loadAdminLoans(); }">
                            <span>📚</span> Library Admin
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="sidebar-item">
                        <a class="sidebar-link" href="#history" onclick="switchTab(event, 'history')">
                            <span>📜</span> History
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="sidebar-footer" style="position: relative; z-index: 9999;">
                <a class="sidebar-link logout-btn" href="javascript:void(0)" onclick="handleLogout();" style="cursor: pointer; display: flex; align-items: center; width: 100%;">
                    <span>🚀</span> Sign Out
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <header class="top-bar">
                <div class="page-title">
                    <span style="color: #64748b; font-weight: 500;">Hello,</span> Admin
                </div>
                <div class="user-actions">
                    <div class="notification-wrapper">
                        <div class="notification-icon" onclick="toggleNotifications()" title="Notifications">
                            🔔<span id="notifBadge" class="badge" style="display:none;">0</span>
                        </div>
                        <div id="notifDropdown" class="notif-dropdown">
                            <div class="notif-header">Department Notifications</div>
                            <div id="notifList" class="notif-list">
                                <div class="notif-empty">No new notifications</div>
                            </div>
                        </div>
                    </div>
                    <div class="user-avatar" title="Admin Profile">👤</div>
                </div>
            </header>

            <?php if (!$is_library): ?>
            <!-- Announcement Ticker -->
            <div class="announcement-ticker-container">
                <div class="ticker-badge">📢 Announcement</div>
                <div class="ticker-wrap">
                    <div id="announcementTickerList" class="ticker-items">
                        Loading latest announcements...
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Profile Section -->
            <section id="profile" class="tab-content" style="display: none;">
                <div class="acad-header">
                    <div>
                        <h1>Administrative Profile</h1>
                        <p style="color: #64748b; font-weight: 500;">Departmental authority and system access credentials</p>
                    </div>
                </div>

                <div class="profile-card-acad">
                    <div class="header-strip" style="background: #0f172a;"></div>
                    <div class="body">
                        <div class="acad-avatar admin-avatar"><?php echo isset($_SESSION['username']) ? strtoupper(substr($_SESSION['username'], 0, 1)) : 'A'; ?></div>
                        <div style="flex: 1;">
                            <table class="acad-info-table">
                                <tr>
                                    <td class="label">System Username</td>
                                    <td class="value"><?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'admin'; ?></td>
                                </tr>
                                <tr>
                                    <td class="label">Administrative Role</td>
                                    <td class="value">Department Administrator</td>
                                </tr>
                                <tr>
                                    <td class="label">Assigned Academic Unit</td>
                                    <td class="value"><?php echo $_SESSION['department']; ?></td>
                                </tr>
                                <tr>
                                    <td class="label">Authority Level</td>
                                    <td class="value">Level 2 (Departmental Oversight)</td>
                                </tr>
                                <tr>
                                    <td class="label">Current Status</td>
                                    <td class="value"><span class="status-dot active"></span> Active & Authenticated</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <?php if (!$is_library): ?>
            <!-- Students Section -->
            <section id="students" class="tab-content" style="display: none;">
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <h2 style="margin: 0; font-size: 18px;">Department Student Roster</h2>
                            <span style="color: #4361ee; font-size: 13px; font-weight: bold; background: #eef2ff; padding: 4px 10px; border-radius: 20px; border: 1px solid #e0e7ff; display: inline-block; margin-top: 5px;">
                                Oversight Unit: <?php echo $_SESSION['department']; ?>
                            </span>
                        </div>
                        <button class="btn btn-success" onclick="openAdminAddStudentModal()" style="background: #10b981; border: none; font-size: 13px; font-weight: 600; padding: 8px 16px; border-radius: 8px; color: white; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                            <span>➕</span> Add Student
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Filter and Search Controls -->
                        <div class="student-filter-controls" style="display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; align-items: center;">
                            <div style="flex: 1; min-width: 250px; position: relative;">
                                <input type="text" id="studentSearchInput" placeholder="🔍 Search student name, ID, or email..." oninput="filterAdminStudents()" style="width: 100%; padding: 14px 16px 14px 44px; border-radius: 14px; font-size: 14px; background: rgba(20, 20, 22, 0.8) !important; border: 1px solid rgba(255, 255, 255, 0.08) !important; color: white !important; box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2); transition: all 0.3s ease;">
                                <span style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; font-size: 16px;">🔍</span>
                            </div>
                            <div style="width: 240px; position: relative;">
                                <select id="studentStatusFilter" onchange="filterAdminStudents()" style="width: 100%; padding: 14px 16px; border-radius: 14px; font-size: 14px; background: rgba(20, 20, 22, 0.8) !important; border: 1px solid rgba(255, 255, 255, 0.08) !important; color: white !important; box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2); cursor: pointer; transition: all 0.3s ease;">
                                    <option value="all">📁 All Clearance Statuses</option>
                                    <option value="issued">🎓 Issued Certificate</option>
                                    <option value="approved">✅ Approved</option>
                                    <option value="pending">⏳ Pending</option>
                                    <option value="dues">💸 Has Dues</option>
                                    <option value="not_started">💤 Not Started</option>
                                </select>
                            </div>
                            <button class="btn btn-secondary" onclick="resetStudentFilters()" style="padding: 14px 20px; font-size: 14px; border-radius: 14px; font-weight: 600; cursor: pointer; border: 1px solid rgba(255, 255, 255, 0.08) !important; background: rgba(255, 255, 255, 0.05) !important; color: white !important; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                                🔄 Reset Filters
                            </button>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="activity-table">
                                <thead>
                                    <tr style="border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 600;">
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Academic CGPA</th>
                                        <th>Clearance Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="adminStudentsTableBody">
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 40px; color: #94a3b8;">
                                            <div style="width: 30px; height: 30px; border: 3px solid #f1f5f9; border-top: 3px solid #4361ee; border-radius: 50%; margin: 0 auto 10px; animation: spin 1s linear infinite;"></div>
                                            Loading student list...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Requests Section -->
            <section id="requests" class="tab-content" style="display: <?php echo !$is_library ? 'block' : 'none'; ?>;">
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="stats-icon bg-pending">⏳</div>
                        <div class="stats-info">
                            <h3 id="pendingCount">0</h3>
                            <p>Pending Requests</p>
                        </div>
                    </div>
                    <div class="stats-card">
                        <div class="stats-icon bg-approved">✅</div>
                        <div class="stats-info">
                            <h3 id="approvedCount">0</h3>
                            <p>Approved Requests</p>
                        </div>
                    </div>
                    <div class="stats-card">
                        <div class="stats-icon bg-total">👥</div>
                        <div class="stats-info">
                            <h3 id="totalRequestsCount">0</h3>
                            <p>Total Requests</p>
                        </div>
                    </div>
                </div>

                <!-- Page Header -->
                <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h1>Clearance Requests</h1>
                        <p>Review and approve/reject student clearance requests</p>
                    </div>
                    <?php if ($is_library): ?>
                    <!-- Library Request Filters -->
                    <div id="libraryRequestFilters" style="display: flex; gap: 10px; align-items: center;">
                        <input type="text" id="libraryRequestSearchInput" placeholder="Search student, book, ISBN..." style="padding: 8px 16px; font-size: 13px; width: 220px; border-radius: 10px; border: 1px solid #cbd5e1; margin: 0; background: white; color: black;" oninput="filterLibraryRequests()">
                        <select id="libraryRequestTypeFilter" style="padding: 8px 16px; font-size: 13px; border-radius: 10px; border: 1px solid #cbd5e1; margin: 0; background: white; color: black;" onchange="filterLibraryRequests()">
                            <option value="all">All Requests</option>
                            <option value="Pending Borrow">Borrow Requests</option>
                            <option value="Pending Renewal">Renewal Requests</option>
                            <option value="Pending Return">Return Requests</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <div id="requestsContainer">
                    <div style="text-align: center; padding: 80px 20px; background: white; border-radius: 24px; border: 1px dashed #e2e8f0;">
                        <div style="font-size: 60px; margin-bottom: 20px; filter: grayscale(1); opacity: 0.5;">📋</div>
                        <h3 style="color: #1e293b; margin-bottom: 10px;">Scanning for Requests...</h3>
                        <p style="color: #64748b; font-size: 15px;">Please wait while we fetch the latest clearance requests from your department.</p>
                        <div style="width: 40px; height: 40px; border: 3px solid #f1f5f9; border-top: 3px solid #4361ee; border-radius: 50%; margin: 20px auto; animation: spin 1s linear infinite;"></div>
                    </div>
                </div>
            </section>

            <style>
                @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            </style>

            <?php if (!$is_library): ?>
            <!-- Announcements Section -->
            <section id="announcements" class="tab-content" style="display: none;">
                <div class="card broadcast-card">
                    <div class="card-header">
                        <h2>Broadcast New Announcement</h2>
                    </div>
                    <div class="card-body">
                        <form id="announcementForm" onsubmit="event.preventDefault(); broadcastAnnouncement();">
                            <div class="form-group">
                                <label for="annTitle">Announcement Title:</label>
                                <input type="text" id="annTitle" placeholder="e.g., Final Clearance Deadline" required>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="annStartDate">Show From (Optional):</label>
                                    <input type="datetime-local" id="annStartDate" style="width: 100%; box-sizing: border-box;">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="annEndDate">Show Until (Optional):</label>
                                    <input type="datetime-local" id="annEndDate" style="width: 100%; box-sizing: border-box;">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="annMessage">Message Content:</label>
                                <textarea id="annMessage" placeholder="Type your broadcast message here..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-full">✨ Send Broadcast to All Students</button>
                        </form>
                    </div>
                </div>

                <div class="card list-card" style="margin-top: 30px;">
                    <div class="card-header">
                        <h2>Recent Announcements</h2>
                    </div>
                    <div class="card-body">
                        <div id="announcementsList" class="ann-list">
                            <p style="text-align: center; color: #94a3b8; padding: 40px;">No announcements broadcasted yet</p>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <?php if (!$is_library): ?>
            <!-- Reports Section -->
            <section id="reports" class="tab-content" style="display: none;">
                <div class="reports-grid">
                    <div class="card chart-card">
                        <div class="card-header">
                            <h2>Clearance Distribution</h2>
                        </div>
                        <div class="card-body">
                            <canvas id="clearanceChart" height="250"></canvas>
                        </div>
                    </div>

                    <div class="card financial-card">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <h2>Financial Overview</h2>
                            <button class="btn btn-secondary btn-sm" onclick="exportStudentData()">📥 Export CSV</button>
                        </div>
                        <div class="card-body">
                            <div class="financial-stats">
                                <div class="fin-stat">
                                    <span class="fin-label">Total Dues</span>
                                    <span class="fin-value" id="repTotalDues">₹0.00</span>
                                </div>
                                <div class="fin-stat collected">
                                    <span class="fin-label">Total Collected</span>
                                    <span class="fin-value" id="repTotalCollected">₹0.00</span>
                                </div>
                                <div class="fin-stat pending">
                                    <span class="fin-label">Outstanding</span>
                                    <span class="fin-value" id="repOutstanding">₹0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card activity-card" style="margin-top: 30px;">
                    <div class="card-header">
                        <h2>Recent Department Activity</h2>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="activity-table">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Action</th>
                                        <th>Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody id="reportActivityTable">
                                    <tr>
                                        <td colspan="4" style="text-align: center; padding: 30px; color: #94a3b8;">No recent activity</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Messages Section -->
            <section id="messages" class="tab-content" style="display: none;">
                <div class="card chat-card">
                    <div class="card-header">
                        <h2>Student Communications</h2>
                    </div>
                    <div class="card-body">
                        <div class="chat-container">
                            <div class="student-chat-list" id="studentChatList">
                                <p style="text-align: center; color: #94a3b8; padding: 20px;">No messages found</p>
                            </div>
                            <div class="chat-view" id="activeChatView">
                                <div class="chat-placeholder">
                                    <div class="chat-icon">💬</div>
                                    <h3>Select a student to start chatting</h3>
                                    <p>Discuss specific issues regarding their clearance request.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- History Section -->
            <section id="history" class="tab-content" style="display: none;">
                <div id="historyContainer">
                    <div style="text-align: center; padding: 50px;">
                        <div style="font-size: 40px; margin-bottom: 15px;">⏳</div>
                        <p style="color: #999; font-size: 16px;">Loading history...</p>
                    </div>
                </div>
            </section>

            <?php if ((isset($_SESSION['department']) && $_SESSION['department'] === 'Library') || $_SESSION['role'] === 'super_admin'): ?>
            <!-- Library Admin Section -->
            <section id="library-admin" class="tab-content" style="display: <?php echo $is_library ? 'block' : 'none'; ?>;">
                <div class="card" style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; padding: 25px;">
                    <div>
                        <h2 style="margin: 0; font-size: 20px; color: #0f172a;">📚 Library Administration Portal</h2>
                        <p style="color: #64748b; margin: 5px 0 0 0; font-size: 14px;">Manage book inventory and check active student book borrows</p>
                    </div>
                    <button class="btn btn-success" onclick="openAddBookModal()" style="background: #10b981; border: none; font-size: 13px; font-weight: 600; padding: 10px 18px; border-radius: 8px; color: white; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                        <span>➕</span> Add New Book
                    </button>
                </div>

                <!-- Book Inventory List -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                        <h2 style="margin: 0;">Library Book Inventory</h2>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="text" id="adminBookSearchInput" placeholder="Search title, author, isbn..." style="padding: 8px 16px; font-size: 13px; width: 220px; border-radius: 10px; margin: 0;" oninput="filterAdminBooks()">
                            <select id="adminBookStatusFilter" style="padding: 8px 16px; font-size: 13px; border-radius: 10px; margin: 0;" onchange="filterAdminBooks()">
                                <option value="all">All Books</option>
                                <option value="available">Available</option>
                                <option value="out_of_stock">Out of Stock</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="activity-table">
                                <thead>
                                    <tr style="border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 600;">
                                        <th>Cover</th>
                                        <th>Book Title</th>
                                        <th>Author</th>
                                        <th>ISBN</th>
                                        <th>Total Copies</th>
                                        <th>Available Copies</th>
                                        <th style="text-align: center;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="adminBooksTableBody">
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 20px; color: #94a3b8;">Loading inventory...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Active Borrowed Books Queue -->
                <div class="card" style="margin-top: 25px;">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                        <h2 style="margin: 0;">Active Book Loans & Overdues</h2>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="text" id="adminLoanSearchInput" placeholder="Search student, title, UID..." style="padding: 8px 16px; font-size: 13px; width: 220px; border-radius: 10px; margin: 0;" oninput="filterAdminLoans()">
                            <select id="adminLoanStatusFilter" style="padding: 8px 16px; font-size: 13px; border-radius: 10px; margin: 0;" onchange="filterAdminLoans()">
                                <option value="all">All Loans</option>
                                <option value="Active">Active</option>
                                <option value="Overdue">Overdue</option>
                                <option value="Pending Renewal">Pending Renewal</option>
                                <option value="Pending Return">Pending Return</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="activity-table">
                                <thead>
                                    <tr style="border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 600;">
                                        <th>Student</th>
                                        <th>Book Details</th>
                                        <th>Borrow Date</th>
                                        <th>Due Date</th>
                                        <th>Fine Assessed</th>
                                        <th>Status</th>
                                        <th style="text-align: center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="adminLoansTableBody">
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 20px; color: #94a3b8;">No active borrows</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>
        </main>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Reject Clearance Request</h2>
                <button class="modal-close" onclick="closeRejectModal()">&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label for="adminRejectionReason">Rejection Reason (Required):</label>
                    <textarea id="adminRejectionReason" placeholder="Explain why you're rejecting this request..." required></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
                <button id="submitRejectBtn" class="btn btn-danger">Reject Request</button>
            </div>
        </div>
    </div>

    <!-- History Details Modal -->
    <div id="historyDetailsModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2>Request Details</h2>
                <button class="modal-close" onclick="closeHistoryDetailsModal()">&times;</button>
            </div>

            <div class="modal-body">
                <div id="historyDetailsContent">
                    <!-- Details will be populated here -->
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeHistoryDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Message Modal -->
    <div id="messageModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2>💬 Student Message</h2>
                <button class="modal-close" onclick="closeMessageModal()">&times;</button>
            </div>

            <div class="modal-body">
                <div id="messageContent" style="background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 4px solid #2196F3; line-height: 1.6; font-size: 16px;">
                    <!-- Message will be populated here -->
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeMessageModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Student Dues Details Modal -->
    <div id="studentDuesModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2>📊 Financial & Clearance Dues Details</h2>
                <button class="modal-close" onclick="closeStudentDuesModal()">&times;</button>
            </div>

            <div class="modal-body">
                <div class="profile-card-acad" style="margin-bottom: 20px;">
                    <div class="body" style="padding: 15px; display: flex; align-items: center; gap: 15px;">
                        <div class="acad-avatar" style="width: 50px; height: 50px; font-size: 20px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: bold;">👤</div>
                        <div>
                            <h3 id="dueModalStudentName" style="margin: 0; color: #1e293b; font-size: 18px;">Student Name</h3>
                            <p id="dueModalStudentId" style="margin: 3px 0 0 0; color: #64748b; font-size: 14px; font-weight: 500;">ID: CS101</p>
                        </div>
                    </div>
                </div>

                <div class="card" style="padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; background: white; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);">
                    <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 16px; color: #1e293b; border-bottom: 2px solid #f1f5f9; padding-bottom: 8px;">Dues Breakdown</h3>
                    
                    <div id="duesBreakdownContent">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeStudentDuesModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Add Student Modal -->
    <div id="adminAddStudentModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2>➕ Add New Student to <?php echo $_SESSION['department']; ?></h2>
                <button class="modal-close" onclick="closeAdminAddStudentModal()">&times;</button>
            </div>

            <div class="modal-body">
                <form id="adminAddStudentForm" onsubmit="submitAdminAddStudent(event)">
                    <div class="form-group">
                        <label for="admNewStudentId">Student ID (Unique)</label>
                        <input type="text" id="admNewStudentId" placeholder="e.g. STU123" required>
                    </div>
                    <div class="form-group">
                        <label for="admNewStudentName">Full Name</label>
                        <input type="text" id="admNewStudentName" placeholder="e.g. Rachel Green" required>
                    </div>
                    <div class="form-group">
                        <label for="admNewStudentEmail">Email Address</label>
                        <input type="email" id="admNewStudentEmail" placeholder="e.g. rachel@university.edu" required>
                    </div>
                    <div class="form-group">
                        <label for="admNewStudentPhone">Phone Number</label>
                        <input type="text" id="admNewStudentPhone" placeholder="e.g. 01912345678">
                    </div>
                    <div class="form-group">
                        <label for="admNewStudentCgpa">Academic CGPA</label>
                        <input type="number" id="admNewStudentCgpa" placeholder="e.g. 3.75" step="0.01" min="0" max="4.00" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeAdminAddStudentModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">Save Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Book Modal -->
    <div id="addBookModal" class="modal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header">
                <h2>➕ Add New Book to Library</h2>
                <button class="modal-close" onclick="closeAddBookModal()">&times;</button>
            </div>

            <div class="modal-body">
                <form id="addBookForm" onsubmit="submitAddBook(event)">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="newBookTitle" style="display: block; font-weight: bold; margin-bottom: 5px; font-size: 14px;">Book Title</label>
                        <input type="text" id="newBookTitle" placeholder="e.g. Introduction to Algorithms" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: white; color: black;">
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="newBookAuthor" style="display: block; font-weight: bold; margin-bottom: 5px; font-size: 14px;">Author Name</label>
                        <input type="text" id="newBookAuthor" placeholder="e.g. Thomas H. Cormen" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: white; color: black;">
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="newBookIsbn" style="display: block; font-weight: bold; margin-bottom: 5px; font-size: 14px;">ISBN Code (Unique)</label>
                        <input type="text" id="newBookIsbn" placeholder="e.g. 978-0262033848" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: white; color: black;">
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="newBookCopies" style="display: block; font-weight: bold; margin-bottom: 5px; font-size: 14px;">Number of Copies</label>
                        <input type="number" id="newBookCopies" placeholder="e.g. 5" min="1" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: white; color: black;">
                    </div>
                    <div class="modal-footer" style="display:flex; justify-content: flex-end; gap: 10px; padding-top: 15px;">
                        <button type="button" class="btn btn-secondary" onclick="closeAddBookModal()" style="padding: 10px 20px; background: #e2e8f0; color: #1e293b; border: none; border-radius: 8px; cursor: pointer;">Cancel</button>
                        <button type="submit" class="btn btn-success" style="padding: 10px 20px; background: #10b981; color: white; border: none; cursor: pointer; border-radius: 8px;">Save Book</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Custom Library Loan Action Modal -->
    <div id="libraryLoanActionModal" class="modal">
        <div class="modal-content" style="max-width: 450px; border-radius: 20px;">
            <div class="modal-header">
                <h2 id="libModalTitle">📖 Approve Borrow Request</h2>
                <button class="modal-close" onclick="closeLibraryLoanActionModal()">&times;</button>
            </div>
            <div class="modal-body" style="padding: 20px 25px;">
                <div id="libModalText" style="font-size: 15px; margin-bottom: 20px; line-height: 1.5; color: #ffffff;"></div>
                <div id="libModalDurationField" class="form-group" style="display: none; margin-bottom: 20px;">
                    <label for="libModalDurationInput" style="display: block; font-weight: bold; margin-bottom: 8px; font-size: 14px; color: #cbd5e1;">Borrow Duration (Days):</label>
                    <input type="number" id="libModalDurationInput" min="1" style="width: 100%; padding: 12px 16px; border-radius: 12px; font-size: 14px; box-sizing: border-box; background: rgba(20, 20, 22, 0.8); border: 1px solid rgba(255, 255, 255, 0.08); color: white;">
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 12px; padding: 15px 25px; border-top: 1px solid rgba(255, 255, 255, 0.06); background: transparent;">
                <button class="btn btn-secondary" onclick="closeLibraryLoanActionModal()" style="padding: 10px 20px;">Cancel</button>
                <button id="libModalConfirmBtn" class="btn btn-success" style="padding: 10px 20px;">Confirm</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>window.IS_LIBRARY_ADMIN = <?php echo $is_library ? 'true' : 'false'; ?>;</script>
    <script src="../assets/js/script.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/admin_premium.js?v=<?php echo time(); ?>"></script>
    <script>
    // Load admin dashboard on page load
    document.addEventListener('DOMContentLoaded', () => {
        loadAdminDashboard();
        <?php if ($is_library): ?>
        if (typeof loadAdminLibraryCatalog === 'function') {
            loadAdminLibraryCatalog();
            loadAdminLoans();
        }
        <?php endif; ?>
    });

    // Tab switching
    function switchTab(event, tabName) {
        event.preventDefault();

        document.querySelectorAll('.sidebar-link').forEach(link => {
            link.classList.remove('active');
        });

        event.target.classList.add('active');
        
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.style.display = 'none';
        });
        
        // Show selected tab
        const selectedTab = document.getElementById(tabName);
        if (selectedTab) {
            selectedTab.style.display = 'block';
            
            // Load history when history tab is clicked
            if (tabName === 'history') {
                loadAdminHistory();
            } else if (tabName === 'announcements' && typeof loadAnnouncements === 'function') {
                loadAnnouncements();
            } else if (tabName === 'reports' && typeof loadReports === 'function') {
                loadReports();
            } else if (tabName === 'messages' && typeof loadChats === 'function') {
                loadChats();
            } else if (tabName === 'students' && typeof loadAdminStudents === 'function') {
                loadAdminStudents();
            }
        }
    }
    </script>
</body>

</html>
