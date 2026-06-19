<?php
require_once '../config/session.php';
requireRole('super_admin');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - Online Clearance Cloud</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/admin_premium.css?v=<?php echo time(); ?>">
</head>

<body data-page="super-admin-dashboard">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-top">
                <div class="logo-circle">👑</div>
                <span class="role-badge">SUPER ADMIN</span>
            </div>

            <ul class="sidebar-menu">
                <li class="sidebar-item">
                    <a class="sidebar-link active" href="#approved-requests" onclick="switchTab(event, 'approved-requests')">
                        <span>🎓</span> Issue Certificates
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="#departments" onclick="switchTab(event, 'departments')">
                        <span>🏢</span> Departments
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
                <li class="sidebar-item">
                    <a class="sidebar-link" href="#profile" onclick="switchTab(event, 'profile')">
                        <span>👤</span> Profile
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link" href="#history" onclick="switchTab(event, 'history')">
                        <span>📜</span> History
                    </a>
                </li>
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
                <div class="page-title">Super Admin Dashboard</div>
                <div class="user-actions">
                    <div class="notification-wrapper">
                        <div class="notification-icon" onclick="toggleNotifications()" title="Notifications">
                            🔔<span id="notifBadge" class="badge" style="display:none;">0</span>
                        </div>
                        <div id="notifDropdown" class="notif-dropdown">
                            <div class="notif-header">System Notifications</div>
                            <div id="notifList" class="notif-list">
                                <div class="notif-empty">No new notifications</div>
                            </div>
                        </div>
                    </div>
                    <div class="user-avatar">👑</div>
                </div>
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

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stats-card">
                    <div class="stats-icon bg-pending">⏳</div>
                    <div class="stats-info">
                        <h3 id="superPendingCount">0</h3>
                        <p>Awaiting Final Approval</p>
                    </div>
                </div>
                <div class="stats-card">
                    <div class="stats-icon bg-approved">📜</div>
                    <div class="stats-info">
                        <h3 id="superCertCount">0</h3>
                        <p>Certificates Issued</p>
                    </div>
                </div>
                <div class="stats-card">
                    <div class="stats-icon bg-total">🏢</div>
                    <div class="stats-info">
                        <h3 id="superDeptCount">7</h3>
                        <p>Departments Active</p>
                    </div>
                </div>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <h1>Issue Clearance Certificates</h1>
                <p>Review approved requests and generate clearance certificates</p>
            </div>

            <!-- Departments Section -->
            <section id="departments" class="tab-content" style="display: none;">
                <div class="card" style="padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 25px; background: white; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h1 style="font-size: 22px; margin: 0 0 5px 0; color: #0f172a;">Institutional Departments Overview</h1>
                        <p style="color: #64748b; margin: 0; font-size: 14px;">Review student roster, aggregate progress, and clearance status by department</p>
                    </div>
                    <div style="display: flex; gap: 12px;">
                        <button class="btn btn-success" onclick="openAddStudentModal()" style="background: #10b981; border: none; font-size: 13px; font-weight: 600; padding: 10px 18px; border-radius: 8px; color: white; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                            <span>➕</span> Assign Student
                        </button>
                        <button class="btn btn-primary" onclick="openAddAdminModal()" style="background: #3b82f6; border: none; font-size: 13px; font-weight: 600; padding: 10px 18px; border-radius: 8px; color: white; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                            <span>➕</span> Assign Admin
                        </button>
                    </div>
                </div>

                <!-- Department Cards Row -->
                <div id="superDeptCardsContainer" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <!-- Cards will be dynamically generated here -->
                </div>

                <!-- Department Student Roster Card -->
                <div class="card" style="border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); background: white; margin-top: 25px;">
                    <div class="card-header" style="padding: 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                        <h2 style="margin: 0; font-size: 18px; color: #0f172a;" id="deptRosterTitle">Select a Department to View Students</h2>
                        <div>
                            <select id="superDeptSelect" class="btn btn-secondary" style="background: white; border: 1px solid #cbd5e1; color: #1e293b; font-size: 14px; padding: 8px 16px; border-radius: 8px; cursor: pointer; outline: none; transition: border 0.2s;" onchange="loadSuperDeptStudents(this.value)">
                                <option value="All">All Departments</option>
                                <option value="Computer Science">Computer Science</option>
                                <option value="Business Administration">Business Administration</option>
                                <option value="Engineering">Engineering</option>
                                <option value="Medicine">Medicine</option>
                                <option value="Law">Law</option>
                                <option value="Psychology">Psychology</option>
                                <option value="Library">Library</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body" style="padding: 20px;">
                        <!-- Filter and Search Controls -->
                        <div class="student-filter-controls" style="display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; align-items: center;">
                            <div style="flex: 1; min-width: 250px; position: relative;">
                                <input type="text" id="superStudentSearchInput" placeholder="🔍 Search student name, ID, or email..." oninput="filterSuperStudents()" style="width: 100%; padding: 14px 16px 14px 44px; border-radius: 14px; font-size: 14px; background: rgba(20, 20, 22, 0.8) !important; border: 1px solid rgba(255, 255, 255, 0.08) !important; color: white !important; box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2); transition: all 0.3s ease;">
                                <span style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; font-size: 16px;">🔍</span>
                            </div>
                            <div style="width: 240px; position: relative;">
                                <select id="superStudentStatusFilter" onchange="filterSuperStudents()" style="width: 100%; padding: 14px 16px; border-radius: 14px; font-size: 14px; background: rgba(20, 20, 22, 0.8) !important; border: 1px solid rgba(255, 255, 255, 0.08) !important; color: white !important; box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2); cursor: pointer; transition: all 0.3s ease;">
                                    <option value="all">📁 All Clearance Statuses</option>
                                    <option value="issued">🎓 Issued Certificate</option>
                                    <option value="approved">✅ Approved</option>
                                    <option value="pending">⏳ Pending</option>
                                    <option value="dues">💸 Has Dues</option>
                                    <option value="not_started">💤 Not Started</option>
                                </select>
                            </div>
                            <button class="btn btn-secondary" onclick="resetSuperFilters()" style="padding: 14px 20px; font-size: 14px; border-radius: 14px; font-weight: 600; cursor: pointer; border: 1px solid rgba(255, 255, 255, 0.08) !important; background: rgba(255, 255, 255, 0.05) !important; color: white !important; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                                🔄 Reset Filters
                            </button>
                        </div>
                        
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                                <thead>
                                    <tr style="border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <th style="padding: 12px 10px;">Student ID</th>
                                        <th style="padding: 12px 10px;">Name</th>
                                        <th style="padding: 12px 10px;">Department</th>
                                        <th style="padding: 12px 10px;">Email</th>
                                        <th style="padding: 12px 10px;">CGPA</th>
                                        <th style="padding: 12px 10px;">Clearance Status</th>
                                        <th style="padding: 12px 10px; text-align: center;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="superDeptStudentsTableBody">
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">
                                            <div style="width: 30px; height: 30px; border: 3px solid #f1f5f9; border-top: 3px solid #eab308; border-radius: 50%; margin: 0 auto 10px; animation: spin 1s linear infinite;"></div>
                                            Loading student list...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Department Administrators Card -->
                <div class="card" style="border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); background: white; margin-top: 25px;">
                    <div class="card-header" style="padding: 20px; border-bottom: 1px solid #e2e8f0;">
                        <h2 style="margin: 0; font-size: 18px; color: #0f172a;" id="deptAdminsTitle">Department Administrators</h2>
                    </div>
                    <div class="card-body" style="padding: 20px;">
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                                <thead>
                                    <tr style="border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <th style="padding: 12px 10px;">Admin Username</th>
                                        <th style="padding: 12px 10px;">Assigned Department</th>
                                        <th style="padding: 12px 10px;">Role</th>
                                        <th style="padding: 12px 10px; text-align: center;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="superDeptAdminsTableBody">
                                    <tr>
                                        <td colspan="4" style="text-align: center; padding: 40px; color: #94a3b8;">
                                            <div style="width: 30px; height: 30px; border: 3px solid #f1f5f9; border-top: 3px solid #3b82f6; border-radius: 50%; margin: 0 auto 10px; animation: spin 1s linear infinite;"></div>
                                            Loading administrators...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Announcements Section -->
            <section id="announcements" class="tab-content" style="display: none;">
                <div class="card" style="padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 25px; background: white;">
                    <h2 style="margin-top: 0; margin-bottom: 20px; font-size: 20px; color: #0f172a;">📢 Broadcast Campus Announcement</h2>
                    <form id="superAnnouncementForm" onsubmit="broadcastSuperAnnouncement(event)">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div class="form-group">
                                <label for="superAnnTitle" style="display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px;">Announcement Title</label>
                                <input type="text" id="superAnnTitle" placeholder="e.g. End of Semester Fee Clearance" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                            </div>
                            <div class="form-group">
                                <label for="superAnnDept" style="display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px;">Target Department</label>
                                <select id="superAnnDept" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; cursor: pointer; box-sizing: border-box; background: white;">
                                    <option value="All">All Departments (Global)</option>
                                    <option value="Computer Science">Computer Science</option>
                                    <option value="Business Administration">Business Administration</option>
                                    <option value="Engineering">Engineering</option>
                                    <option value="Medicine">Medicine</option>
                                    <option value="Law">Law</option>
                                    <option value="Psychology">Psychology</option>
                                    <option value="Library">Library</option>
                                </select>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div class="form-group">
                                <label for="superAnnStartDate" style="display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px;">Show From (Optional)</label>
                                <input type="datetime-local" id="superAnnStartDate" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                            </div>
                            <div class="form-group">
                                <label for="superAnnEndDate" style="display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px;">Show Until (Optional)</label>
                                <input type="datetime-local" id="superAnnEndDate" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label for="superAnnMessage" style="display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px;">Message Content</label>
                            <textarea id="superAnnMessage" placeholder="Type your detailed announcement message here..." required style="width: 100%; height: 100px; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; resize: vertical;"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" style="background: #3b82f6; border: none; font-size: 14px; font-weight: 600; padding: 10px 22px; border-radius: 8px; color: white; cursor: pointer;">📣 Broadcast Bulletin</button>
                    </form>
                </div>

                <div class="card" style="border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); background: white;">
                    <div class="card-header" style="padding: 20px; border-bottom: 1px solid #e2e8f0;">
                        <h2 style="margin: 0; font-size: 18px; color: #0f172a;">Active Campus Bulletins</h2>
                    </div>
                    <div class="card-body" style="padding: 20px;">
                        <div id="superAnnouncementsList" class="ann-list" style="display: flex; flex-direction: column; gap: 15px;">
                            <p style="text-align: center; color: #94a3b8; padding: 40px;">No announcements broadcasted yet</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Reports Section -->
            <section id="reports" class="tab-content" style="display: none;">
                <div class="card" style="padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 25px; background: white; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h1 style="font-size: 22px; margin: 0 0 5px 0; color: #0f172a;">📊 Institutional Financial & Clearance Reports</h1>
                        <p style="color: #64748b; margin: 0; font-size: 14px;">Monitor overall clearance distribution and outstanding dues by academic unit</p>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <select id="superReportDeptSelect" class="btn btn-secondary" style="background: white; border: 1px solid #cbd5e1; color: #1e293b; font-size: 14px; padding: 8px 16px; border-radius: 8px; cursor: pointer; outline: none;" onchange="loadSuperReportsData(this.value)">
                            <option value="All">All Departments</option>
                            <option value="Computer Science">Computer Science</option>
                            <option value="Business Administration">Business Administration</option>
                            <option value="Engineering">Engineering</option>
                            <option value="Medicine">Medicine</option>
                            <option value="Law">Law</option>
                            <option value="Psychology">Psychology</option>
                            <option value="Library">Library</option>
                        </select>
                        <button class="btn btn-primary" onclick="exportSuperStudentData()" style="background: #3b82f6; border: none; font-size: 13px; font-weight: 600; padding: 9px 18px; border-radius: 8px; color: white; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                            <span>📥</span> Export CSV
                        </button>
                    </div>
                </div>

                <div class="reports-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
                    <div class="card chart-card" style="border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); background: white;">
                        <div class="card-header" style="padding: 20px; border-bottom: 1px solid #e2e8f0;">
                            <h2 style="margin: 0; font-size: 18px; color: #0f172a;">Clearance Distribution</h2>
                        </div>
                        <div class="card-body" style="padding: 20px; display: flex; align-items: center; justify-content: center; min-height: 250px;">
                            <canvas id="superClearanceChart" height="250" style="max-height: 250px; width: 100%;"></canvas>
                        </div>
                    </div>

                    <div class="card financial-card" style="border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); background: white;">
                        <div class="card-header" style="padding: 20px; border-bottom: 1px solid #e2e8f0;">
                            <h2 style="margin: 0; font-size: 18px; color: #0f172a;">Financial Overview</h2>
                        </div>
                        <div class="card-body" style="padding: 25px;">
                            <div class="financial-stats" style="display: flex; flex-direction: column; gap: 20px;">
                                <div class="fin-stat" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                                    <span class="fin-label" style="color: #64748b; font-weight: 500;">Total Dues Assessed</span>
                                    <span class="fin-value" id="superRepTotalDues" style="font-weight: bold; font-size: 18px; color: #0f172a;">₹0.00</span>
                                </div>
                                <div class="fin-stat collected" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                                    <span class="fin-label" style="color: #64748b; font-weight: 500;">Total Collected Payments</span>
                                    <span class="fin-value" id="superRepTotalCollected" style="font-weight: bold; font-size: 18px; color: #16a34a;">₹0.00</span>
                                </div>
                                <div class="fin-stat pending" style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 4px;">
                                    <span class="fin-label" style="color: #64748b; font-weight: 500;">Outstanding Balance</span>
                                    <span class="fin-value" id="superRepOutstanding" style="font-weight: bold; font-size: 18px; color: #dc2626;">₹0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card activity-card" style="border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); background: white;">
                    <div class="card-header" style="padding: 20px; border-bottom: 1px solid #e2e8f0;">
                        <h2 style="margin: 0; font-size: 18px; color: #0f172a;">Recent Students Activity</h2>
                    </div>
                    <div class="card-body" style="padding: 20px;">
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                                <thead>
                                    <tr style="border-bottom: 2px solid #e2e8f0; color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase;">
                                        <th style="padding: 12px 10px;">Student ID</th>
                                        <th style="padding: 12px 10px;">Name</th>
                                        <th style="padding: 12px 10px;">Department</th>
                                        <th style="padding: 12px 10px;">Action Status</th>
                                        <th style="padding: 12px 10px;">Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody id="superReportActivityTable">
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 30px; color: #94a3b8;">No recent activity</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Profile Section -->
            <section id="profile" class="tab-content" style="display: none;">
                <div class="card" style="padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px;">
                        <div style="width: 80px; height: 80px; background: #eab308; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px;">👑</div>
                        <div>
                            <h2 style="margin: 0;"><?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'super_admin'; ?></h2>
                            <p style="color: #666; margin: 5px 0;">System Super Administrator</p>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <label style="display: block; font-size: 12px; color: #888; text-transform: uppercase;">Role</label>
                            <p style="font-weight: bold;"><?php echo $_SESSION['role']; ?></p>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; color: #888; text-transform: uppercase;">Permissions</label>
                            <p style="font-weight: bold;">Full System Access</p>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; color: #888; text-transform: uppercase;">Organization</label>
                            <p style="font-weight: bold;">University Administration</p>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; color: #888; text-transform: uppercase;">Last Active</label>
                            <p style="font-weight: bold;"><?php echo date('F j, Y'); ?></p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Approved Requests Section -->
            <section id="approved-requests" class="tab-content" style="display: block;">
                <!-- Filter and Search Controls for Approved Requests -->
                <div class="student-filter-controls" style="display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; align-items: center;">
                    <div style="flex: 1; min-width: 250px; position: relative;">
                        <input type="text" id="superReqSearchInput" placeholder="🔍 Search student name or ID..." oninput="filterApprovedRequests()" style="width: 100%; padding: 14px 16px 14px 44px; border-radius: 14px; font-size: 14px; background: rgba(20, 20, 22, 0.8) !important; border: 1px solid rgba(255, 255, 255, 0.08) !important; color: white !important; box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2); transition: all 0.3s ease;">
                        <span style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; font-size: 16px;">🔍</span>
                    </div>
                    <div style="width: 240px; position: relative;">
                        <select id="superReqDeptFilter" onchange="filterApprovedRequests()" style="width: 100%; padding: 14px 16px; border-radius: 14px; font-size: 14px; background: rgba(20, 20, 22, 0.8) !important; border: 1px solid rgba(255, 255, 255, 0.08) !important; color: white !important; box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2); cursor: pointer; transition: all 0.3s ease;">
                            <option value="all">🏢 All Departments</option>
                            <option value="Computer Science">Computer Science</option>
                            <option value="Business Administration">Business Administration</option>
                            <option value="Engineering">Engineering</option>
                            <option value="Medicine">Medicine</option>
                            <option value="Law">Law</option>
                            <option value="Psychology">Psychology</option>
                            <option value="Library">Library</option>
                        </select>
                    </div>
                    <button class="btn btn-secondary" onclick="resetApprovedFilters()" style="padding: 14px 20px; font-size: 14px; border-radius: 14px; font-weight: 600; cursor: pointer; border: 1px solid rgba(255, 255, 255, 0.08) !important; background: rgba(255, 255, 255, 0.05) !important; color: white !important; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;">
                        🔄 Reset Filters
                    </button>
                </div>
                
                <div id="approvedRequestsContainer">
                    <div style="text-align: center; padding: 50px;">
                        <div style="font-size: 40px; margin-bottom: 15px;">⏳</div>
                        <p style="color: #999; font-size: 16px;">Loading approved requests...</p>
                    </div>
                </div>
            </section>

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
                                    <p>Discuss final decision adjustments with students.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- History Section -->
            <section id="history" class="tab-content" style="display: none;">
                <div id="superAdminHistoryContainer">
                    <div style="text-align: center; padding: 50px;">
                        <div style="font-size: 40px; margin-bottom: 15px;">⏳</div>
                        <p style="color: #999; font-size: 16px;">Loading history...</p>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Reject Modal -->
    <div id="superAdminRejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Reject Clearance Request</h2>
                <button class="modal-close" onclick="closeSuperAdminRejectModal()">&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label for="superAdminRejectionReason">Rejection Reason (Required):</label>
                    <textarea id="superAdminRejectionReason" placeholder="Explain why you're rejecting this request..." required></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeSuperAdminRejectModal()">Cancel</button>
                <button id="submitSuperAdminRejectBtn" class="btn btn-danger">Reject Request</button>
            </div>
        </div>
    </div>

    <!-- History Details Modal -->
    <div id="superAdminHistoryDetailsModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2>Request Details</h2>
                <button class="modal-close" onclick="closeSuperAdminHistoryDetailsModal()">&times;</button>
            </div>

            <div class="modal-body">
                <div id="superAdminHistoryDetailsContent">
                    <!-- Details will be populated here -->
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeSuperAdminHistoryDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Add/Assign Student Modal -->
    <div id="addStudentModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2>➕ Assign / Add New Student</h2>
                <button class="modal-close" onclick="closeAddStudentModal()">&times;</button>
            </div>

            <div class="modal-body">
                <form id="addStudentForm" onsubmit="submitAddStudent(event)">
                    <div class="form-group">
                        <label for="newStudentId">Student ID (Unique)</label>
                        <input type="text" id="newStudentId" placeholder="e.g. STU062" required>
                    </div>
                    <div class="form-group">
                        <label for="newStudentName">Full Name</label>
                        <input type="text" id="newStudentName" placeholder="e.g. Rachel Green" required>
                    </div>
                    <div class="form-group">
                        <label for="newStudentEmail">Email Address</label>
                        <input type="email" id="newStudentEmail" placeholder="e.g. rachel@university.edu" required>
                    </div>
                    <div class="form-group">
                        <label for="newStudentPhone">Phone Number</label>
                        <input type="text" id="newStudentPhone" placeholder="e.g. 01912345678">
                    </div>
                    <div class="form-group">
                        <label for="newStudentDept">Department</label>
                        <select id="newStudentDept" required>
                            <option value="Computer Science">Computer Science</option>
                            <option value="Business Administration">Business Administration</option>
                            <option value="Engineering">Engineering</option>
                            <option value="Medicine">Medicine</option>
                            <option value="Law">Law</option>
                            <option value="Psychology">Psychology</option>
                            <option value="Library">Library</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="newStudentCgpa">Academic CGPA</label>
                        <input type="number" id="newStudentCgpa" placeholder="e.g. 3.75" step="0.01" min="0" max="4.00" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeAddStudentModal()">Cancel</button>
                        <button type="submit" class="btn btn-success">Save Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add/Assign Admin Modal -->
    <div id="addAdminModal" class="modal">
        <div class="modal-content" style="max-width: 450px;">
            <div class="modal-header">
                <h2>➕ Assign / Create Department Admin</h2>
                <button class="modal-close" onclick="closeAddAdminModal()">&times;</button>
            </div>

            <div class="modal-body">
                <form id="addAdminForm" onsubmit="submitAddAdmin(event)">
                    <div class="form-group">
                        <label for="newAdminUsername">Username (Unique)</label>
                        <input type="text" id="newAdminUsername" placeholder="e.g. eee_admin" required>
                    </div>
                    <div class="form-group">
                        <label for="newAdminPassword">Password</label>
                        <input type="password" id="newAdminPassword" placeholder="Minimum 4 characters" required>
                    </div>
                    <div class="form-group">
                        <label for="newAdminDept">Assigned Department</label>
                        <select id="newAdminDept" required>
                            <option value="Computer Science">Computer Science</option>
                            <option value="Business Administration">Business Administration</option>
                            <option value="Engineering">Engineering</option>
                            <option value="Medicine">Medicine</option>
                            <option value="Law">Law</option>
                            <option value="Psychology">Psychology</option>
                            <option value="Library">Library</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeAddAdminModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Admin</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/script.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/super_admin_premium.js?v=<?php echo time(); ?>"></script>
    <script>
    // Load super admin dashboard on page load
    document.addEventListener('DOMContentLoaded', function() {
        if(typeof loadSuperAdminDashboard === 'function') loadSuperAdminDashboard();
    });

    // Tab switching
    function switchTab(event, tabName) {
        event.preventDefault();

        document.querySelectorAll('.sidebar-link').forEach(link => {
            link.classList.remove('active');
        });

        event.currentTarget.classList.add('active');
        
        // Hide all tabs
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.style.display = 'none';
        });
        
        // Show selected tab
        const selectedTab = document.getElementById(tabName);
        if (selectedTab) {
            selectedTab.style.display = 'block';
            
            // Clear super admin chat interval if switching away from messages
            if (tabName !== 'messages' && typeof superChatInterval !== 'undefined' && superChatInterval) {
                clearInterval(superChatInterval);
                superChatInterval = null;
            }
            
            // Trigger specific tab loads
            if (tabName === 'history') {
                loadSuperAdminHistory();
            } else if (tabName === 'departments' && typeof loadSuperAdminDepartments === 'function') {
                loadSuperAdminDepartments();
            } else if (tabName === 'announcements' && typeof loadSuperAnnouncements === 'function') {
                loadSuperAnnouncements();
            } else if (tabName === 'reports' && typeof loadSuperReportsData === 'function') {
                loadSuperReportsData('All');
            } else if (tabName === 'messages' && typeof loadSuperChats === 'function') {
                loadSuperChats();
            }
        }
    }
    </script>
</body>

</html>
