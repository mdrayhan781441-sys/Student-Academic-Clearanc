/**
 * Admin Premium Features JS
 * Handles Announcements, Reports, and Messaging
 */

// Initialize Chart variable
let clearanceChart = null;

// Tab switching extension
const originalSwitchTab = window.switchTab;
window.switchTab = function(event, tabName) {
    if (typeof originalSwitchTab === 'function') {
        originalSwitchTab(event, tabName);
    }
    
    // Load specific data based on tab
    if (tabName === 'announcements') {
        loadAnnouncements();
    } else if (tabName === 'reports') {
        loadReports();
    } else if (tabName === 'messages') {
        loadChats();
    }
};

// --- Announcements ---
async function loadAnnouncements() {
    const list = document.getElementById('announcementsList');
    if (!list) return;
    try {
        const data = await apiCall('admin_announcements.php');
        
        if (data.announcements && data.announcements.length > 0) {
            list.innerHTML = data.announcements.map(ann => {
                let timeframeHtml = "";
                if (ann.start_date || ann.end_date) {
                    const startText = ann.start_date ? new Date(ann.start_date).toLocaleString() : "Immediate";
                    const endText = ann.end_date ? new Date(ann.end_date).toLocaleString() : "Never";
                    timeframeHtml = `<div style="font-size: 11px; color: #a1a1aa; margin-top: 8px; font-style: italic;">📅 Scheduled: ${startText} to ${endText}</div>`;
                }
                return `
                    <div class="ann-item">
                        <div class="ann-header">
                            <span class="ann-title">${ann.title}</span>
                            <span class="ann-date">${new Date(ann.created_at).toLocaleDateString()}</span>
                        </div>
                        <p class="ann-msg">${ann.message}</p>
                        ${timeframeHtml}
                        <div class="ann-footer">By: ${ann.author}</div>
                    </div>
                `;
            }).join("");
        } else {
            list.innerHTML = '<p style="text-align: center; color: #94a3b8; padding: 40px;">No announcements broadcasted yet</p>';
        }
    } catch (e) {
        console.error("Announcements Error:", e);
    }
}

async function broadcastAnnouncement() {
    const titleEl = document.getElementById('annTitle');
    const messageEl = document.getElementById('annMessage');
    const startEl = document.getElementById('annStartDate');
    const endEl = document.getElementById('annEndDate');
    if (!titleEl || !messageEl) return;
    const title = titleEl.value;
    const message = messageEl.value;
    const start_date = startEl ? startEl.value : '';
    const end_date = endEl ? endEl.value : '';
    
    try {
        const result = await apiCall('admin_announcements.php', 'POST', { 
            title, 
            message,
            start_date,
            end_date
        });
        
        if (result.success) {
            showAlert("Announcement broadcasted successfully!", "success");
            const form = document.getElementById('announcementForm');
            if (form) form.reset();
            loadAnnouncements();
        } else {
            showAlert(result.message, "danger");
        }
    } catch (e) {
        showAlert("Failed to broadcast announcement", "danger");
    }
}

// --- Reports & Charts ---
async function loadReports() {
    const repTotalDues = document.getElementById('repTotalDues');
    if (!repTotalDues) return;
    try {
        const data = await apiCall('admin_reports.php');
        
        if (data.success) {
            // Update Financials
            repTotalDues.textContent = '₹' + data.financials.total_dues.toLocaleString();
            const repTotalCollected = document.getElementById('repTotalCollected');
            if (repTotalCollected) repTotalCollected.textContent = '₹' + data.financials.total_collected.toLocaleString();
            const repOutstanding = document.getElementById('repOutstanding');
            if (repOutstanding) repOutstanding.textContent = '₹' + data.financials.outstanding.toLocaleString();
            
            // Update Activity Table
            const table = document.getElementById('reportActivityTable');
            if (table && data.recent_activity.length > 0) {
                table.innerHTML = data.recent_activity.map(act => `
                    <tr>
                        <td>${act.student_id}</td>
                        <td>${act.name}</td>
                        <td><span class="status-badge status-${act.status.toLowerCase()}">${act.status}</span></td>
                        <td>${new Date(act.updated_at).toLocaleString()}</td>
                    </tr>
                `).join("");
            }
            
            // Initialize/Update Chart
            initClearanceChart(data.clearance_stats);
        }
    } catch (e) {
        console.error("Reports Error:", e);
    }
}

function initClearanceChart(stats) {
    const chartEl = document.getElementById('clearanceChart');
    if (!chartEl) return;
    const ctx = chartEl.getContext('2d');
    
    if (clearanceChart) {
        clearanceChart.destroy();
    }
    
    clearanceChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Cleared', 'Pending', 'Not Started'],
            datasets: [{
                data: [stats.cleared, stats.pending, stats.not_started],
                backgroundColor: ['#10b981', '#f59e0b', '#cbd5e1'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            },
            cutout: '70%'
        }
    });
}

// --- Messaging ---
let currentAdminChatId = null;
let adminChatInterval = null;

async function loadChats() {
    try {
        const pendingData = await apiCall('admin_clearance_list.php');
        const historyData = await apiCall('admin_history.php');
        
        const list = document.getElementById('studentChatList');
        if (!list) return;

        let allRequests = [];
        if (pendingData && pendingData.success && pendingData.requests) {
            allRequests = allRequests.concat(pendingData.requests);
        }
        if (historyData && historyData.success && historyData.history) {
            allRequests = allRequests.concat(historyData.history);
        }
        
        if (allRequests.length > 0) {
            const students = [];
            const seen = new Set();
            allRequests.forEach(r => {
                const s = r.student;
                if (s && !seen.has(s.id)) {
                    students.push({
                        requestId: r.id,
                        studentId: s.id,
                        name: s.name,
                        status: r.status,
                        unreadCount: r.unread_count || 0
                    });
                    seen.add(s.id);
                }
            });
            
            list.innerHTML = students.map(s => {
                const unreadBadge = s.unreadCount > 0 ? `<span class="unread-badge" style="background: #ef4444; color: white; border-radius: 12px; padding: 2px 8px; font-size: 11px; font-weight: bold; margin-left: auto; display: flex; align-items: center; justify-content: center; min-width: 18px; height: 18px;">${s.unreadCount}</span>` : '';
                return `
                    <div class="chat-student-item" id="chat-student-${s.requestId}" onclick="openChat('${s.requestId}', '${s.name}', '${s.studentId}')" style="display: flex; align-items: center; width: 100%;">
                        <div class="chat-avatar">${s.name.charAt(0).toUpperCase()}</div>
                        <div class="chat-info" style="flex: 1;">
                            <div class="chat-name">${s.name}</div>
                            <div class="chat-last-msg">ID: ${s.studentId} (${s.status})</div>
                        </div>
                        ${unreadBadge}
                    </div>
                `;
            }).join("");
        } else {
            list.innerHTML = '<p style="text-align: center; color: #94a3b8; padding: 40px;">No students available for chat</p>';
        }
    } catch (e) {
        console.error("Chat List Error:", e);
    }
}

function openChat(requestId, name, student_id) {
    currentAdminChatId = requestId;
    
    // Clear unread badge in UI
    const activeItem = document.getElementById(`chat-student-${requestId}`);
    if (activeItem) {
        const badge = activeItem.querySelector(".unread-badge");
        if (badge) badge.remove();
    }
    
    // Clear any existing polling interval
    if (adminChatInterval) {
        clearInterval(adminChatInterval);
    }
    
    const view = document.getElementById('activeChatView');
    if (!view) return;

    view.innerHTML = `
        <div class="chat-window" style="display: flex; flex-direction: column; height: 100%;">
            <div class="chat-header" style="background: var(--primary-dark, #1e3a8a); color: white; padding: 15px; border-radius: 12px 12px 0 0;">
                <h3 style="margin: 0;">Chat with ${name}</h3>
                <span style="font-size: 11px; opacity: 0.8;">Institutional Registration ID: ${student_id}</span>
            </div>
            <div class="chat-messages" id="adminChatMessages" style="flex: 1; overflow-y: auto; background: #f8fafc; padding: 15px; display: flex; flex-direction: column; gap: 10px; min-height: 250px; max-height: 350px; border-left: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;">
                <div style="text-align: center; color: #94a3b8; padding: 40px; font-size: 13px;">Loading conversation history...</div>
            </div>
            <div class="chat-input-area" style="display: flex; gap: 10px; padding: 15px; background: white; border-radius: 0 0 12px 12px; border: 1px solid #e2e8f0; border-top: none;">
                <input type="text" placeholder="Type a message..." id="adminChatInput" style="flex: 1; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;" onkeypress="if(event.key==='Enter')sendMessage('${requestId}')">
                <button class="btn btn-primary" onclick="sendMessage('${requestId}')" style="padding: 10px 20px;">Send</button>
            </div>
        </div>
    `;

    // Load messages initially and start polling
    loadAdminChatMessages();
    adminChatInterval = setInterval(loadAdminChatMessages, 5000);
}

async function loadAdminChatMessages() {
    if (!currentAdminChatId) return;
    try {
        const data = await apiCall(`get_messages.php?request_id=${currentAdminChatId}`);
        const container = document.getElementById("adminChatMessages");
        if (!container) return;

        if (data.success && data.messages) {
            const filteredMessages = data.messages.filter(msg => {
                if (window.IS_LIBRARY_ADMIN) {
                    if (msg.sender_role === 'admin' && msg.sender_dept === 'Library') return true;
                    if (msg.sender_role === 'student' && msg.recipient_role === 'library') return true;
                    return false;
                } else {
                    if (msg.sender_role === 'admin' && msg.sender_dept !== 'Library') return true;
                    if (msg.sender_role === 'student' && (msg.recipient_role === 'admin' || msg.recipient_role === null)) return true;
                    return false;
                }
            });

            if (filteredMessages.length === 0) {
                container.innerHTML = '<div style="text-align: center; color: #94a3b8; padding: 40px; font-size: 13px;">No messages exchanged yet. Send a note to start.</div>';
                return;
            }
            
            const html = filteredMessages.map(msg => {
                const isSentByMe = (msg.sender_role === 'admin' || msg.sender_role === 'super_admin');
                const bubbleClass = isSentByMe ? 'sent' : 'received';
                const alignment = isSentByMe ? 'align-self: flex-end; background: var(--primary-color, #2563eb); color: white;' : 'align-self: flex-start; background: #e2e8f0; color: #1e293b;';
                
                return `
                    <div class="msg ${bubbleClass}" style="max-width: 70%; padding: 10px 15px; border-radius: 12px; font-size: 14px; word-wrap: break-word; ${alignment}">
                        <div style="font-size: 10px; opacity: 0.8; margin-bottom: 4px; font-weight: bold;">${msg.sender_name}</div>
                        <div>${msg.message}</div>
                        <div style="font-size: 9px; opacity: 0.6; text-align: right; margin-top: 4px;">${new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
                    </div>
                `;
            }).join("");
            
            // Check if scroll is at bottom before updating
            const shouldScroll = container.scrollTop + container.clientHeight >= container.scrollHeight - 20 || container.querySelector("div").textContent.includes("Loading");
            
            container.innerHTML = html;
            
            if (shouldScroll) {
                container.scrollTop = container.scrollHeight;
            }
        }
    } catch (e) {
        console.error("Failed to load admin chat messages:", e);
    }
}

async function sendMessage(requestId) {
    if (!currentAdminChatId) return;
    const input = document.getElementById("adminChatInput");
    if (!input) return;
    
    const msg = input.value.trim();
    if (!msg) return;

    try {
        // Optimistic UI updates
        const container = document.getElementById("adminChatMessages");
        if (container) {
            const tempDiv = document.createElement('div');
            tempDiv.className = 'msg sent';
            tempDiv.style.cssText = 'max-width: 70%; padding: 10px 15px; border-radius: 12px; font-size: 14px; word-wrap: break-word; align-self: flex-end; background: var(--primary-color, #2563eb); color: white; opacity: 0.7;';
            tempDiv.innerHTML = `
                <div style="font-size: 10px; opacity: 0.8; margin-bottom: 4px; font-weight: bold;">Sending...</div>
                <div>${msg}</div>
            `;
            container.appendChild(tempDiv);
            container.scrollTop = container.scrollHeight;
        }

        input.value = '';

        const result = await apiCall('send_message.php', 'POST', {
            request_id: currentAdminChatId,
            message: msg
        });

        if (result && result.success) {
            loadAdminChatMessages();
        } else {
            showAlert("Failed to send message: " + (result.message || "Unknown error"), "danger");
        }
    } catch (e) {
        console.error("Failed to send message:", e);
        showAlert("Failed to connect to messaging server", "danger");
    }
}

// --- Export Data ---
async function exportStudentData() {
    try {
        const data = await apiCall('admin_reports.php?export=true');
        
        if (data.recent_activity) {
            let csv = "Student ID,Name,Status,Timestamp\n";
            data.recent_activity.forEach(row => {
                csv += `${row.student_id},${row.name},${row.status},${row.updated_at}\n`;
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('hidden', '');
            a.setAttribute('href', url);
            a.setAttribute('download', 'clearance_report.csv');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    } catch (e) {
        console.error("Export Error:", e);
    }
}

// --- Load Department Students ---
let adminCachedStudents = [];

async function loadAdminStudents() {
    const tbody = document.getElementById("adminStudentsTableBody");
    if (!tbody) return;

    try {
        const data = await apiCall("admin_student_list.php");
        if (data && data.success) {
            adminCachedStudents = data.students || [];
            // Re-apply search/filter automatically so current states persist on actions!
            filterAdminStudents();
        } else {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 30px; color: #ef4444;">Failed to load student roster.</td></tr>`;
        }
    } catch (e) {
        console.error("Failed to load department student roster:", e);
        tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 30px; color: #ef4444;">Failed to load student roster.</td></tr>`;
    }
}

function renderAdminStudents(students) {
    const tbody = document.getElementById("adminStudentsTableBody");
    if (!tbody) return;

    if (students.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">🔍 No matching students found.</td></tr>`;
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

        const isApprovedOrIssued = s.clearance_status === 'Approved' || s.clearance_status === 'Issued Certificate';
        const approveBtn = !isApprovedOrIssued ? 
            `<button class="btn btn-success" onclick="directApproveStudent(${s.id})" style="padding: 6px 12px; font-size: 12px; border-radius: 8px; font-weight: bold; background: #22c55e; color: white; border: none; cursor: pointer; margin-right: 5px;">✓ Approve</button>` : 
            `<button class="btn btn-secondary" disabled style="padding: 6px 12px; font-size: 12px; border-radius: 8px; background: #e2e8f0; color: #94a3b8; border: none; cursor: not-allowed; font-weight: bold; margin-right: 5px;">✓ Approved</button>`;
        
        const detailsBtn = `<button class="btn btn-primary" onclick="showStudentDuesModal(${s.id})" style="padding: 6px 12px; font-size: 12px; border-radius: 8px; font-weight: bold; background: #4f46e5; color: white; border: none; cursor: pointer; margin-right: 5px;">📄 Details</button>`;
        
        const deleteBtn = `<button class="btn btn-danger" onclick="removeStudentByAdmin(${s.id})" style="padding: 6px 12px; font-size: 12px; border-radius: 8px; font-weight: bold; background: #ef4444; color: white; border: none; cursor: pointer;">❌ Remove</button>`;

        return `
            <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 12px 10px; font-weight: bold; color: #1e293b;">${s.student_id}</td>
                <td style="padding: 12px 10px;">${s.name}</td>
                <td style="padding: 12px 10px; color: #64748b;">${s.email}</td>
                <td style="padding: 12px 10px; font-weight: 500;">${s.cgpa.toFixed(2)}</td>
                <td style="padding: 12px 10px;">
                    <span class="status-badge ${badgeClass}" style="${badgeStyle}">
                        ${s.clearance_status}
                    </span>
                </td>
                <td style="padding: 12px 10px;">
                    <div style="display: flex; align-items: center; justify-content: flex-start; flex-wrap: nowrap;">
                        ${detailsBtn}
                        ${approveBtn}
                        ${deleteBtn}
                    </div>
                </td>
            </tr>
        `;
    }).join("");
}

function filterAdminStudents() {
    const searchInput = document.getElementById("studentSearchInput");
    const statusFilter = document.getElementById("studentStatusFilter");
    
    const searchVal = searchInput ? searchInput.value.toLowerCase().trim() : "";
    const statusVal = statusFilter ? statusFilter.value : "all";

    const filtered = adminCachedStudents.filter(s => {
        // 1. Search filter matches name, ID, or email
        const matchesSearch = 
            s.name.toLowerCase().includes(searchVal) ||
            s.student_id.toLowerCase().includes(searchVal) ||
            s.email.toLowerCase().includes(searchVal);

        // 2. Clearance Status filter matches status
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

    renderAdminStudents(filtered);
}

function resetStudentFilters() {
    const searchInput = document.getElementById("studentSearchInput");
    const statusFilter = document.getElementById("studentStatusFilter");
    
    if (searchInput) searchInput.value = "";
    if (statusFilter) statusFilter.value = "all";
    
    renderAdminStudents(adminCachedStudents);
}

async function directApproveStudent(studentId) {
    if (!confirm("Are you sure you want to directly approve this student? This will clear all their department dues instantly and forward their clearance to the Super Admin.")) return;
    
    try {
        const response = await fetch("../api/direct_approve.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ student_id: studentId })
        });
        const result = await response.json();
        
        if (result.success) {
            showAlert(result.message || "Student cleared successfully!", "success");
            loadAdminStudents(); // Reload the roster
            if (typeof loadAdminDashboard === 'function') loadAdminDashboard(); // Reload pending requests & stats
        } else {
            showAlert(result.message || "Failed to clear student directly.", "danger");
        }
    } catch (e) {
        showAlert("Connection error: " + e.message, "danger");
    }
}

function closeStudentDuesModal() {
    const modal = document.getElementById("studentDuesModal");
    if (modal) modal.style.display = "none";
}

async function showStudentDuesModal(studentId) {
    const modal = document.getElementById("studentDuesModal");
    if (!modal) return;
    
    document.getElementById("dueModalStudentName").textContent = "Loading...";
    document.getElementById("dueModalStudentId").textContent = "";
    document.getElementById("duesBreakdownContent").innerHTML = `
        <div style="text-align: center; padding: 30px;">
            <div style="width: 30px; height: 30px; border: 3px solid #f1f5f9; border-top: 3px solid #4f46e5; border-radius: 50%; margin: 0 auto; animation: spin 1s linear infinite;"></div>
        </div>
    `;
    
    modal.style.display = "block";
    
    try {
        const response = await fetch(`../api/get_student_dues.php?student_id=${studentId}`);
        const result = await response.json();
        
        if (result.success) {
            document.getElementById("dueModalStudentName").textContent = result.student.name;
            document.getElementById("dueModalStudentId").textContent = `ID: ${result.student.student_id} | ${result.student.department}`;
            
            const dues = result.dues;
            
            let tableHtml = `
                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <thead>
                        <tr style="border-bottom: 2px solid #f1f5f9; text-align: left; color: #64748b;">
                            <th style="padding: 10px 5px;">Category</th>
                            <th style="padding: 10px 5px; text-align: right;">Amount Due</th>
                            <th style="padding: 10px 5px; text-align: right;">Amount Paid</th>
                            <th style="padding: 10px 5px; text-align: right;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            const categories = [
                { name: 'Tuition Fee', key: 'tuition', icon: '🎓' },
                { name: 'Hostel Fee', key: 'hostel', icon: '🏢' },
                { name: 'Library Fee', key: 'library', icon: '📚' }
            ];
            
            categories.forEach(cat => {
                const item = dues[cat.key];
                const balance = item.due - item.paid;
                const statusText = balance <= 0 ? 
                    '<span style="color: #16a34a; font-weight: 600; background: #dcfce7; padding: 2px 8px; border-radius: 9999px; font-size: 11px;">Cleared</span>' : 
                    `<span style="color: #dc2626; font-weight: 600; background: #fee2e2; padding: 2px 8px; border-radius: 9999px; font-size: 11px;">Owes ₹${balance.toFixed(2)}</span>`;
                
                tableHtml += `
                    <tr style="border-bottom: 1px solid #f1f5f9; color: #334155;">
                        <td style="padding: 12px 5px; font-weight: 500;">${cat.icon} ${cat.name}</td>
                        <td style="padding: 12px 5px; text-align: right;">₹${item.due.toFixed(2)}</td>
                        <td style="padding: 12px 5px; text-align: right;">₹${item.paid.toFixed(2)}</td>
                        <td style="padding: 12px 5px; text-align: right;">${statusText}</td>
                    </tr>
                `;
            });
            
            const totalBalance = dues.total.due - dues.total.paid;
            const totalStatusText = totalBalance <= 0 ?
                '<span style="color: #16a34a; font-weight: bold; font-size: 16px;">Fully Cleared</span>' :
                `<span style="color: #dc2626; font-weight: bold; font-size: 16px;">Pending ₹${totalBalance.toFixed(2)}</span>`;
            
            tableHtml += `
                    </tbody>
                </table>
                <div style="margin-top: 20px; padding-top: 15px; border-top: 2px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span style="color: #64748b; font-size: 13px; font-weight: 500;">Overall Financial Status:</span>
                        <div style="margin-top: 4px;">${totalStatusText}</div>
                    </div>
                </div>
            `;
            
            document.getElementById("duesBreakdownContent").innerHTML = tableHtml;
        } else {
            document.getElementById("duesBreakdownContent").innerHTML = `
                <p style="color: #dc2626; font-weight: bold; text-align: center; padding: 20px;">
                    Failed to load dues details: ${result.message}
                </p>
            `;
        }
    } catch (e) {
        document.getElementById("duesBreakdownContent").innerHTML = `
            <p style="color: #dc2626; font-weight: bold; text-align: center; padding: 20px;">
                Connection error occurred.
            </p>
        `;
    }
}

function openAdminAddStudentModal() {
    const modal = document.getElementById("adminAddStudentModal");
    if (modal) {
        document.getElementById("adminAddStudentForm").reset();
        modal.style.display = "block";
    }
}

function closeAdminAddStudentModal() {
    const modal = document.getElementById("adminAddStudentModal");
    if (modal) modal.style.display = "none";
}

async function submitAdminAddStudent(event) {
    event.preventDefault();
    
    const studentId = document.getElementById("admNewStudentId").value.trim();
    const name = document.getElementById("admNewStudentName").value.trim();
    const email = document.getElementById("admNewStudentEmail").value.trim();
    const phone = document.getElementById("admNewStudentPhone").value.trim();
    const cgpa = parseFloat(document.getElementById("admNewStudentCgpa").value);
    
    try {
        const response = await fetch("../api/admin_manage_student.php?action=add_student", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                student_id: studentId,
                name: name,
                email: email,
                phone: phone,
                cgpa: cgpa
            })
        });
        const result = await response.json();
        
        if (result.success) {
            showAlert(result.message || "Student added successfully!", "success");
            closeAdminAddStudentModal();
            loadAdminStudents(); // Reload roster
            if (typeof loadAdminDashboard === 'function') loadAdminDashboard(); // Reload statistics
        } else {
            showAlert(result.message || "Failed to add student.", "danger");
        }
    } catch (e) {
        showAlert("Connection error: " + e.message, "danger");
    }
}

async function removeStudentByAdmin(studentId) {
    if (!confirm("⚠️ WARNING: Are you sure you want to completely remove this student? This will delete all their clearances, dues, payments, certificates, and chat history. This action CANNOT be undone!")) return;
    
    try {
        const response = await fetch(`../api/admin_manage_student.php?action=remove_student`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ id: studentId })
        });
        const result = await response.json();
        
        if (result.success) {
            showAlert(result.message || "Student removed successfully!", "success");
            loadAdminStudents(); // Reload the roster table
            if (typeof loadAdminDashboard === 'function') loadAdminDashboard(); // Reload statistics
        } else {
            showAlert(result.message || "Failed to remove student.", "danger");
        }
    } catch (e) {
        showAlert("Connection error: " + e.message, "danger");
    }
}
