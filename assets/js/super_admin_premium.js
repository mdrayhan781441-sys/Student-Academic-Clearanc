// --- Super Admin Premium JavaScript Controller ---

// --- 1. Announcements Management ---
async function loadSuperAnnouncements() {
    const list = document.getElementById("superAnnouncementsList");
    if (!list) return;

    try {
        const response = await fetch("../api/admin_announcements.php");
        const data = await response.json();

        if (data.success && data.announcements && data.announcements.length > 0) {
            list.innerHTML = data.announcements.map(ann => {
                const targetDeptText = ann.department === 'All' ? '📢 All Departments (Global)' : `🏢 ${ann.department}`;
                let timeframeHtml = "";
                if (ann.start_date || ann.end_date) {
                    const startText = ann.start_date ? new Date(ann.start_date).toLocaleString() : "Immediate";
                    const endText = ann.end_date ? new Date(ann.end_date).toLocaleString() : "Never";
                    timeframeHtml = `<div style="font-size: 11px; color: #64748b; margin-top: 8px; font-style: italic;">📅 Scheduled: ${startText} to ${endText}</div>`;
                }
                return `
                    <div class="ann-card-item" style="border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; margin-bottom: 15px; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: relative; border-left: 5px solid #3b82f6;">
                        <div class="ann-card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <span class="ann-card-title" style="font-weight: bold; font-size: 16px; color: #1e293b;">📢 ${ann.title}</span>
                            <span class="ann-card-date" style="font-size: 12px; color: #94a3b8;">${new Date(ann.created_at).toLocaleDateString()}</span>
                        </div>
                        <p class="ann-card-msg" style="color: #475569; font-size: 14px; line-height: 1.6; margin: 0 0 12px 0;">${ann.message}</p>
                        ${timeframeHtml}
                        <div class="ann-card-footer" style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #64748b; border-top: 1px solid #f1f5f9; padding-top: 8px; margin-top: 10px;">
                            <span>Broadcasted by: <strong>${ann.author}</strong></span>
                            <span style="background: #eef2ff; color: #4f46e5; padding: 3px 10px; border-radius: 9999px; font-weight: 600;">${targetDeptText}</span>
                        </div>
                    </div>
                `;
            }).join("");
        } else {
            list.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #94a3b8;">
                    <div style="font-size: 40px; margin-bottom: 15px;">🗞️</div>
                    <p>No campus announcements broadcasted yet.</p>
                </div>
            `;
        }
    } catch (e) {
        console.error("Super Announcements Load Error:", e);
        list.innerHTML = '<p style="text-align: center; color: #ef4444; padding: 40px;">⚠️ Failed to load announcements. Please refresh.</p>';
    }
}

async function broadcastSuperAnnouncement(event) {
    event.preventDefault();

    const title = document.getElementById("superAnnTitle").value.trim();
    const dept = document.getElementById("superAnnDept").value;
    const message = document.getElementById("superAnnMessage").value.trim();
    const startEl = document.getElementById("superAnnStartDate");
    const endEl = document.getElementById("superAnnEndDate");
    const startDate = startEl ? startEl.value : '';
    const endDate = endEl ? endEl.value : '';

    try {
        const response = await fetch("../api/admin_announcements.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                title: title,
                department: dept,
                message: message,
                start_date: startDate,
                end_date: endDate
            })
        });
        const result = await response.json();

        if (result.success) {
            showAlert("Announcement successfully broadcasted!", "success");
            document.getElementById("superAnnouncementForm").reset();
            loadSuperAnnouncements();
        } else {
            showAlert(result.message || "Failed to broadcast announcement", "danger");
        }
    } catch (e) {
        showAlert("Connection error: " + e.message, "danger");
    }
}

// --- 2. Reports & Statistics Controller ---
let superClearanceChartInstance = null;
let currentReportData = null; // Store fetched data locally for exporting

async function loadSuperReportsData(deptFilter) {
    const tableBody = document.getElementById("superReportActivityTable");
    if (!tableBody) return;

    tableBody.innerHTML = `
        <tr>
            <td colspan="5" style="text-align: center; padding: 30px; color: #94a3b8;">
                <div style="width: 25px; height: 25px; border: 3px solid #f1f5f9; border-top: 3px solid #3b82f6; border-radius: 50%; margin: 0 auto 10px; animation: spin 1s linear infinite;"></div>
                Updating statistics...
            </td>
        </tr>
    `;

    try {
        const response = await fetch(`../api/admin_reports.php?department=${encodeURIComponent(deptFilter)}`);
        const data = await response.json();

        if (data.success) {
            currentReportData = data; // Store data reference

            // 1. Render Financial Metrics
            document.getElementById("superRepTotalDues").textContent = `₹${data.financials.total_dues.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            document.getElementById("superRepTotalCollected").textContent = `₹${data.financials.total_collected.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            document.getElementById("superRepOutstanding").textContent = `₹${data.financials.outstanding.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

            // 2. Render Recent Activity Table
            if (data.recent_activity && data.recent_activity.length > 0) {
                tableBody.innerHTML = data.recent_activity.map(row => {
                    let badgeColor = '#ef4444';
                    let badgeBg = '#fee2e2';
                    if (row.status === 'Approved' || row.status === 'Issued Certificate') {
                        badgeColor = '#22c55e';
                        badgeBg = '#dcfce7';
                    } else if (row.status === 'Pending') {
                        badgeColor = '#eab308';
                        badgeBg = '#fef9c3';
                    }

                    return `
                        <tr style="border-bottom: 1px solid #f1f5f9; color: #334155;">
                            <td style="padding: 12px 10px; font-weight: 600; color: #0f172a;">${row.student_id}</td>
                            <td style="padding: 12px 10px;">${row.name}</td>
                            <td style="padding: 12px 10px; font-weight: 500; color: #4b5563;">${row.department}</td>
                            <td style="padding: 12px 10px;">
                                <span style="background: ${badgeBg}; color: ${badgeColor}; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; display: inline-block;">
                                    ${row.status}
                                </span>
                            </td>
                            <td style="padding: 12px 10px; color: #64748b;">${new Date(row.updated_at).toLocaleString()}</td>
                        </tr>
                    `;
                }).join("");
            } else {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px; color: #94a3b8;">
                            <div style="font-size: 32px; margin-bottom: 10px;">📂</div>
                            No recent student clearance activity recorded.
                        </td>
                    </tr>
                `;
            }

            // 3. Render Chart
            const ctx = document.getElementById("superClearanceChart");
            if (ctx) {
                const chartCtx = ctx.getContext('2d');
                
                // Cleanup existing instance
                if (superClearanceChartInstance) {
                    superClearanceChartInstance.destroy();
                }

                const clearedVal = data.clearance_stats.cleared;
                const pendingVal = data.clearance_stats.pending;
                const notStartedVal = data.clearance_stats.not_started;

                if (clearedVal === 0 && pendingVal === 0 && notStartedVal === 0) {
                    // Render empty state
                    superClearanceChartInstance = new Chart(chartCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['No Data Available'],
                            datasets: [{
                                data: [1],
                                backgroundColor: ['#e2e8f0']
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: { position: 'bottom' }
                            }
                        }
                    });
                } else {
                    superClearanceChartInstance = new Chart(chartCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Cleared', 'Pending Review', 'Not Started'],
                            datasets: [{
                                data: [clearedVal, pendingVal, notStartedVal],
                                backgroundColor: ['#22c55e', '#eab308', '#94a3b8'],
                                borderWidth: 2,
                                borderColor: '#ffffff'
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        boxWidth: 12,
                                        font: { weight: 'bold' }
                                    }
                                }
                            }
                        }
                    });
                }
            }

        } else {
            tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 30px; color: #ef4444;">Failed to fetch statistics: ${data.message}</td></tr>`;
        }
    } catch (e) {
        console.error("Super Reports Error:", e);
        tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 30px; color: #ef4444;">Connection error occurred.</td></tr>`;
    }
}

// --- 3. CSV Exporting Functionality ---
async function exportSuperStudentData() {
    if (!currentReportData || !currentReportData.recent_activity || currentReportData.recent_activity.length === 0) {
        showAlert("No clearance activities available to export.", "danger");
        return;
    }

    try {
        let csv = "Student ID,Name,Department,Status,Timestamp\n";
        currentReportData.recent_activity.forEach(row => {
            csv += `"${row.student_id}","${row.name.replace(/"/g, '""')}","${row.department}","${row.status}","${row.updated_at}"\n`;
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.setAttribute('hidden', '');
        a.setAttribute('href', url);
        
        const filename = `super_clearance_report_${currentReportData.department.replace(/\s+/g, '_').toLowerCase()}.csv`;
        a.setAttribute('download', filename);
        
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        showAlert(`Report successfully exported to ${filename}!`, "success");
    } catch (e) {
        console.error("CSV Export Failure:", e);
        showAlert("Failed to export CSV file.", "danger");
    }
}

// --- 4. Super Admin Support Communications (Messages Tab) ---
let currentSuperChatId = null;
let superChatInterval = null;

async function loadSuperChats() {
    try {
        const pendingData = await apiCall('super_admin_list.php');
        const historyData = await apiCall('super_admin_history.php');
        
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
                    <div class="chat-student-item" id="chat-student-${s.requestId}" onclick="openSuperChat('${s.requestId}', '${s.name}', '${s.studentId}')" style="display: flex; align-items: center; width: 100%;">
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
        console.error("Super Chat List Error:", e);
    }
}

function openSuperChat(requestId, name, student_id) {
    currentSuperChatId = requestId;
    
    // Clear unread badge in UI
    const activeItem = document.getElementById(`chat-student-${requestId}`);
    if (activeItem) {
        const badge = activeItem.querySelector(".unread-badge");
        if (badge) badge.remove();
    }
    
    // Clear any existing polling interval
    if (superChatInterval) {
        clearInterval(superChatInterval);
    }
    
    const view = document.getElementById('activeChatView');
    if (!view) return;

    view.innerHTML = `
        <div class="chat-window" style="display: flex; flex-direction: column; height: 100%;">
            <div class="chat-header" style="background: var(--primary-dark, #1e3a8a); color: white; padding: 15px; border-radius: 12px 12px 0 0;">
                <h3 style="margin: 0;">Chat with ${name}</h3>
                <span style="font-size: 11px; opacity: 0.8;">Institutional Registration ID: ${student_id}</span>
            </div>
            <div class="chat-messages" id="superChatMessages" style="flex: 1; overflow-y: auto; background: #f8fafc; padding: 15px; display: flex; flex-direction: column; gap: 10px; min-height: 250px; max-height: 350px; border-left: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;">
                <div style="text-align: center; color: #94a3b8; padding: 40px; font-size: 13px;">Loading conversation history...</div>
            </div>
            <div class="chat-footer" style="padding: 15px; background: white; border-top: 1px solid #e2e8f0; display: flex; gap: 10px; border-radius: 0 0 12px 12px;">
                <input type="text" id="superChatInput" style="flex: 1; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; font-size: 14px;" placeholder="Type a message to ${name}..." onkeypress="if(event.key === 'Enter') sendSuperMessage()">
                <button class="btn btn-primary" style="padding: 10px 20px; font-weight: 600;" onclick="sendSuperMessage()">Send</button>
            </div>
        </div>
    `;
    
    loadSuperChatMessages();
    superChatInterval = setInterval(loadSuperChatMessages, 5000);
}

async function loadSuperChatMessages() {
    if (!currentSuperChatId) return;
    try {
        const data = await apiCall(`get_messages.php?request_id=${currentSuperChatId}`);
        const container = document.getElementById("superChatMessages");
        if (!container) return;

        if (data.success && data.messages) {
            const filteredMessages = data.messages.filter(msg => {
                if (msg.sender_role === 'super_admin') return true;
                if (msg.sender_role === 'student' && msg.recipient_role === 'super_admin') return true;
                return false;
            });

            if (filteredMessages.length === 0) {
                container.innerHTML = '<div style="text-align: center; color: #94a3b8; padding: 40px; font-size: 13px;">No messages exchanged yet. Send a note to start.</div>';
                return;
            }
            
            const html = filteredMessages.map(msg => {
                const isSentByMe = (msg.sender_role === 'super_admin');
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
        console.error("Failed to load super admin chat messages:", e);
    }
}

async function sendSuperMessage() {
    if (!currentSuperChatId) return;
    const input = document.getElementById("superChatInput");
    if (!input) return;
    
    const msg = input.value.trim();
    if (!msg) return;
    
    try {
        const result = await apiCall("send_message.php", "POST", {
            request_id: currentSuperChatId,
            message: msg
        });
        
        if (result.success) {
            input.value = "";
            loadSuperChatMessages();
        }
    } catch (e) {
        console.error("Failed to send super admin message:", e);
        showAlert("Failed to send message", "danger");
    }
}
