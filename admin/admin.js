/**
 * Admin Dashboard Logic
 * File: admin.js
 * Cập nhật: Fixed preview function
 */

//const API_URL = 'http://localhost/doc_management/api';
let monthlyChart, categoryChart;

document.addEventListener('DOMContentLoaded', function() {
    if (!requireAdmin()) return;
    loadDashboard();
});

// ===== TAB SWITCHING =====
function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.style.display = 'none';
    });
    
    document.querySelectorAll('.admin-menu a').forEach(link => {
        link.classList.remove('active');
    });
    
    document.getElementById('tab-' + tabName).style.display = 'block';
    document.getElementById('menu-' + tabName).classList.add('active');
    
    switch(tabName) {
        case 'dashboard': loadDashboard(); break;
        case 'users': loadUsers(); break;
        case 'documents': loadAllDocuments(); break;
        case 'categories': loadCategories(); break;
        case 'logs': loadLogs(); break;
    }
}

// ===== DASHBOARD =====
async function loadDashboard() {
    try {
        const response = await fetch(`${API_URL}/admin.php?action=statistics`, {
            headers: {'Authorization': 'Bearer ' + getAuthToken()}
        });
        
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            
            document.getElementById('stat-total-docs').textContent = data.total_documents;
            document.getElementById('stat-total-users').textContent = data.total_users;
            document.getElementById('stat-total-downloads').textContent = data.total_downloads.toLocaleString();
            
            const currentMonth = new Date().toISOString().slice(0, 7);
            const thisMonth = data.monthly_documents.find(m => m.month === currentMonth);
            document.getElementById('stat-month-docs').textContent = thisMonth ? thisMonth.count : 0;
            
            drawMonthlyChart(data.monthly_documents);
            drawCategoryChart(data.docs_by_category);
            displayTopDocuments(data.top_documents);
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
        alert('Không thể tải dữ liệu dashboard');
    }
}

function drawMonthlyChart(data) {
    const ctx = document.getElementById('monthlyChart');
    
    if (monthlyChart) monthlyChart.destroy();
    
    const months = [];
    const counts = [];
    for (let i = 11; i >= 0; i--) {
        const date = new Date();
        date.setMonth(date.getMonth() - i);
        const monthStr = date.toISOString().slice(0, 7);
        months.push(date.toLocaleDateString('vi-VN', {month: 'short', year: 'numeric'}));
        
        const found = data.find(d => d.month === monthStr);
        counts.push(found ? parseInt(found.count) : 0);
    }
    
    monthlyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: months,
            datasets: [{
                label: 'Số tài liệu mới',
                data: counts,
                backgroundColor: 'rgba(102, 126, 234, 0.8)',
                borderColor: 'rgba(102, 126, 234, 1)',
                borderWidth: 2,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: {display: false} },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {precision: 0}
                }
            }
        }
    });
}

function drawCategoryChart(data) {
    const ctx = document.getElementById('categoryChart');
    
    if (categoryChart) categoryChart.destroy();
    
    const labels = data.map(d => d.name);
    const counts = data.map(d => parseInt(d.count));
    
    categoryChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: counts,
                backgroundColor: [
                    'rgba(102, 126, 234, 0.8)',
                    'rgba(118, 75, 162, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(239, 68, 68, 0.8)'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
}

function displayTopDocuments(documents) {
    const tbody = document.getElementById('topDocsBody');
    
    tbody.innerHTML = documents.map((doc, index) => `
        <tr>
            <td><strong>${index + 1}</strong></td>
            <td>${doc.title}</td>
            <td>👁️ ${doc.view_count}</td>
            <td>⬇️ ${doc.download_count}</td>
            <td>
                <button class="action-btn view" onclick="viewDocument(${doc.id})">Xem</button>
            </td>
        </tr>
    `).join('');
}

// ===== USERS MANAGEMENT =====
async function loadUsers(page = 1) {
    const role = document.getElementById('filterRole')?.value || '';
    const status = document.getElementById('filterStatus')?.value || '';
    
    try {
        const params = new URLSearchParams({
            action: 'users',
            page: page,
            role: role,
            status: status
        });
        
        const response = await fetch(`${API_URL}/admin.php?${params}`, {
            headers: {'Authorization': 'Bearer ' + getAuthToken()}
        });
        
        const result = await response.json();
        
        if (result.success) {
            displayUsers(result.data);
            displayPagination(result.pagination, 'usersPagination', loadUsers);
        }
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

function displayUsers(users) {
    const tbody = document.getElementById('usersTableBody');
    
    tbody.innerHTML = users.map(user => `
        <tr>
            <td>${user.id}</td>
            <td>${user.email}</td>
            <td>${user.full_name}</td>
            <td><span class="badge">${getRoleName(user.role)}</span></td>
            <td>
                <span class="status-badge ${user.is_blocked ? 'blocked' : 'active'}">
                    ${user.is_blocked ? 'Blocked' : 'Active'}
                </span>
            </td>
            <td>${formatDate(user.created_at)}</td>
            <td>
                ${user.is_blocked ? 
                    `<button class="action-btn edit" onclick="unblockUser(${user.id})">Mở khóa</button>` :
                    `<button class="action-btn delete" onclick="blockUser(${user.id})">Khóa</button>`
                }
            </td>
        </tr>
    `).join('');
}

async function blockUser(userId) {
    const reason = prompt('Lý do khóa tài khoản:');
    if (!reason) return;
    
    try {
        const response = await fetch(`${API_URL}/admin.php?action=block-user`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + getAuthToken()
            },
            body: JSON.stringify({user_id: userId, reason: reason})
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Khóa tài khoản thành công!');
            loadUsers();
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

async function unblockUser(userId) {
    if (!confirm('Mở khóa tài khoản này?')) return;
    
    try {
        const response = await fetch(`${API_URL}/admin.php?action=unblock-user`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + getAuthToken()
            },
            body: JSON.stringify({user_id: userId})
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Mở khóa thành công!');
            loadUsers();
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// ===== DOCUMENTS MANAGEMENT =====
async function loadAllDocuments(page = 1) {
    const category = document.getElementById('filterDocCategory')?.value || '';
    
    try {
        const params = new URLSearchParams({
            action: 'all-documents',
            page: page,
            category: category
        });
        
        const response = await fetch(`${API_URL}/admin.php?${params}`, {
            headers: {'Authorization': 'Bearer ' + getAuthToken()}
        });
        
        const result = await response.json();
        
        if (result.success) {
            displayAllDocuments(result.data);
            displayPagination(result.pagination, 'docsPagination', loadAllDocuments);
        }
    } catch (error) {
        console.error('Error loading documents:', error);
    }
}

function displayAllDocuments(docs) {
    const tbody = document.getElementById('docsTableBody');
    
    tbody.innerHTML = docs.map(doc => `
        <tr>
            <td>${doc.id}</td>
            <td>${doc.title}</td>
            <td>${doc.author_name}</td>
            <td>${doc.category_name}</td>
            <td>${formatDate(doc.created_at)}</td>
            <td>⬇️ ${doc.download_count}</td>
            <td>
                <button class="action-btn view" onclick="viewDocument(${doc.id})">Xem</button>
                <button class="action-btn delete" onclick="deleteDocumentAdmin(${doc.id})">Xóa</button>
            </td>
        </tr>
    `).join('');
}
async function deleteDocumentAdmin(docId) {
    // Hỏi xác nhận lần cuối vì hành động này không thể hoàn tác
    const reason = prompt('CẢNH BÁO: Hành động này sẽ xóa vĩnh viễn tài liệu và không thể khôi phục.\nNhập lý do xóa:');
    if (!reason) return;
    
    try {
        const response = await fetch(`${API_URL}/admin.php?action=delete-document`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + getAuthToken()
            },
            body: JSON.stringify({doc_id: docId, reason: reason})
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            // QUAN TRỌNG: Gọi hàm này để làm mới bảng dữ liệu ngay lập tức
            loadAllDocuments(); 
            // Cập nhật lại cả dashboard số liệu nếu đang ở tab dashboard
            loadDashboard(); 
        } else {
            alert(result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Có lỗi xảy ra khi kết nối server');
    }
}

// =========================================================
// XEM TÀI LIỆU TRỰC TIẾP - ĐÃ FIX
// =========================================================

async function viewDocument(id) {
    try {
        const response = await fetch(`${API_URL}/documents.php?action=get&id=${id}`, {
            headers: {'Authorization': 'Bearer ' + getAuthToken()}
        });
        
        const result = await response.json();
        
        if (result.success) {
            showDocumentPreviewModal(result.data);
        } else {
            alert('Không thể tải thông tin tài liệu');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Đã xảy ra lỗi khi tải tài liệu');
    }
}

function showDocumentPreviewModal(doc) {
    let modal = document.getElementById('adminDocPreviewModal');
    
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'adminDocPreviewModal';
        modal.className = 'modal';
        document.body.appendChild(modal);
    }
    
    const previewableFormats = ['pdf', 'docx', 'doc', 'jpg', 'jpeg', 'png', 'gif', 'txt'];
    const canPreview = previewableFormats.includes(doc.file_format.toLowerCase());
    
    modal.innerHTML = `
        <div class="modal-content preview-modal-content">
            <div class="preview-header">
                <h2>📄 ${doc.title}</h2>
                <span class="close" onclick="closeAdminDocPreview()">&times;</span>
            </div>
            
            <div class="preview-info">
                <span class="badge">${doc.category_name}</span>
                <span class="badge secondary">${doc.doc_type_name}</span>
                <span>📄 ${doc.file_format.toUpperCase()}</span>
                <span>👤 ${doc.author_name}</span>
                <span>👁️ ${doc.view_count} lượt xem</span>
                <span>⬇️ ${doc.download_count} lượt tải</span>
            </div>
            
            ${doc.description ? `
                <div class="preview-description">
                    <strong>Mô tả:</strong> ${doc.description}
                </div>
            ` : ''}
            
            <div class="preview-actions">
                ${canPreview ? `
                   
<button onclick="openAdminPreview('${doc.full_path_url}', '${doc.file_format}', '${doc.file_name}')" class="action-btn view">
    👁️ Xem trực tiếp
</button>
                ` : ''}
                <button onclick="downloadAdminDocument('${doc.file_path}', '${doc.file_name}')" class="action-btn edit">
                    ⬇️ Tải xuống
                </button>
                
            </div>
            
            <div id="adminPreviewContainer" style="display: none; margin-top: 20px;"></div>
        </div>
    `;
    
    modal.style.display = 'block';
}

function openAdminPreview(filePath, fileFormat, fileName) {
    const container = document.getElementById('adminPreviewContainer');
    container.style.display = 'block';
    
    // Check if advanced preview exists
    if (typeof previewDocumentAdvanced === 'function') {
        previewDocumentAdvanced(filePath, fileFormat, fileName);
    } else {
        // Use simple preview
        simplePreview(filePath, fileFormat, fileName, container);
    }
}

// Simple preview fallback
function simplePreview(filePath, fileFormat, fileName, container) {
    const format = fileFormat.toLowerCase();
    
    container.innerHTML = '<div style="text-align: center; padding: 40px;"><div class="spinner"></div><p>Đang tải...</p></div>';
    
    if (format === 'pdf') {
        container.innerHTML = `
            <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <iframe src="${filePath}" 
                    style="width: 100%; height: 700px; border: none; border-radius: 8px;">
                </iframe>
            </div>
        `;
    } 
    else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(format)) {
        container.innerHTML = `
            <div style="text-align: center; padding: 20px; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <img src="${filePath}" 
                    alt="${fileName}"
                    style="max-width: 100%; height: auto; border-radius: 8px;">
            </div>
        `;
    }
    else if (format === 'txt') {
        fetch(filePath)
            .then(r => {
                if (!r.ok) throw new Error('Không thể tải file');
                return r.text();
            })
            .then(text => {
                container.innerHTML = `
                    <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                        <pre style="background: #f8f9fa; padding: 20px; border-radius: 8px; 
                            max-height: 600px; overflow-y: auto; white-space: pre-wrap; 
                            font-family: 'Courier New', monospace; line-height: 1.6;">${text}</pre>
                    </div>
                `;
            })
            .catch(err => {
                container.innerHTML = `
                    <div class="preview-fallback" style="text-align: center; padding: 60px;">
                        <h3 style="color: #f59e0b;">⚠️ Không thể tải nội dung file</h3>
                        <p>${err.message}</p>
                        <button onclick="downloadAdminDocument('${filePath}', '${fileName}')" 
                            class="action-btn edit" style="margin-top: 20px;">
                            ⬇️ Tải xuống để xem
                        </button>
                    </div>
                `;
            });
    }
    else {
        container.innerHTML = `
            <div class="preview-fallback" style="text-align: center; padding: 60px; background: #f9fafb; border-radius: 12px;">
                <h3 style="color: #f59e0b; margin-bottom: 16px;">⚠️ Không hỗ trợ xem trực tiếp định dạng ${format.toUpperCase()}</h3>
                <p style="margin-bottom: 24px;">Vui lòng tải xuống để xem nội dung</p>
                <button onclick="downloadAdminDocument('${filePath}', '${fileName}')" 
                    class="action-btn edit">
                    ⬇️ Tải xuống ngay
                </button>
            </div>
        `;
    }
}

function downloadAdminDocument(filePath, fileName) {
    const link = document.createElement('a');
    link.href = filePath;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function closeAdminDocPreview() {
    const modal = document.getElementById('adminDocPreviewModal');
    if (modal) {
        modal.style.display = 'none';
        // Clear preview content
        const container = document.getElementById('adminPreviewContainer');
        if (container) {
            container.innerHTML = '';
            container.style.display = 'none';
        }
    }
}

// ===== CATEGORIES MANAGEMENT =====
async function loadCategories() {
    try {
        const catResponse = await fetch(`${API_URL}/categories.php?action=list-categories`);
        const catResult = await catResponse.json();
        
        if (catResult.success) {
            displayCategoriesTable(catResult.data);
            
            const filterSelect = document.getElementById('filterDocCategory');
            if (filterSelect) {
                filterSelect.innerHTML = '<option value="">Tất cả chuyên ngành</option>' +
                    catResult.data.map(cat => `<option value="${cat.id}">${cat.name}</option>`).join('');
            }
        }
        
        const typeResponse = await fetch(`${API_URL}/categories.php?action=list-types`);
        const typeResult = await typeResponse.json();
        
        if (typeResult.success) {
            displayTypesTable(typeResult.data);
        }
    } catch (error) {
        console.error('Error loading categories:', error);
    }
}

function displayCategoriesTable(categories) {
    const tbody = document.getElementById('categoriesTableBody');
    
    tbody.innerHTML = categories.map(cat => `
        <tr>
            <td>${cat.id}</td>
            <td>${cat.name}</td>
            <td>${cat.description || '-'}</td>
            <td>${formatDate(cat.created_at)}</td>
            <td>
                <button class="action-btn edit" onclick="editCategory(${cat.id}, '${escapeHtml(cat.name)}', '${escapeHtml(cat.description || '')}')">Sửa</button>
                <button class="action-btn delete" onclick="deleteCategory(${cat.id})">Xóa</button>
            </td>
        </tr>
    `).join('');
}

function displayTypesTable(types) {
    const tbody = document.getElementById('typesTableBody');
    
    tbody.innerHTML = types.map(type => `
        <tr>
            <td>${type.id}</td>
            <td>${type.name}</td>
            <td>${type.description || '-'}</td>
            <td>${formatDate(type.created_at)}</td>
            <td>
                <button class="action-btn edit" onclick="editType(${type.id}, '${escapeHtml(type.name)}', '${escapeHtml(type.description || '')}')">Sửa</button>
                <button class="action-btn delete" onclick="deleteType(${type.id})">Xóa</button>
            </td>
        </tr>
    `).join('');
}

// ===== LOGS =====
async function loadLogs() {
    try {
        const response = await fetch(`${API_URL}/admin.php?action=logs`, {
            headers: {'Authorization': 'Bearer ' + getAuthToken()}
        });
        
        const result = await response.json();
        
        if (result.success) {
            displayLogs(result.data);
        }
    } catch (error) {
        console.error('Error loading logs:', error);
    }
}

function displayLogs(logs) {
    const tbody = document.getElementById('logsTableBody');
    
    tbody.innerHTML = logs.map(log => `
        <tr>
            <td>${formatDateTime(log.created_at)}</td>
            <td>${log.admin_name}</td>
            <td><span class="badge">${log.action}</span></td>
            <td>${log.target_type} #${log.target_id}</td>
            <td>${log.reason || '-'}</td>
        </tr>
    `).join('');
}

// ===== UTILITIES =====
function displayPagination(pagination, containerId, loadFunction) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const {page, pages} = pagination;
    
    let html = `<button onclick="${loadFunction.name}(${page - 1})" ${page === 1 ? 'disabled' : ''}>« Trước</button>`;
    
    for (let i = 1; i <= pages; i++) {
        if (i === 1 || i === pages || (i >= page - 2 && i <= page + 2)) {
            html += `<button onclick="${loadFunction.name}(${i})" class="${i === page ? 'active' : ''}">${i}</button>`;
        }
    }
    
    html += `<button onclick="${loadFunction.name}(${page + 1})" ${page === pages ? 'disabled' : ''}>Sau »</button>`;
    
    container.innerHTML = html;
}

function getRoleName(role) {
    const roles = {
        'student': 'Sinh viên',
        'teacher': 'Giảng viên',
        'staff': 'Cán bộ',
        'admin': 'Admin'
    };
    return roles[role] || role;
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('vi-VN');
}

function formatDateTime(dateString) {
    return new Date(dateString).toLocaleString('vi-VN');
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/'/g, "\\'").replace(/"/g, '\\"');
}

// ===== MODAL XỬ LÝ CATEGORIES & TYPES =====
window.addEventListener('DOMContentLoaded', function() {
    const categoryModal = document.getElementById('categoryModal');
    const categoryForm = document.getElementById('categoryForm');
    
    if (categoryForm) {
        categoryForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const id = document.getElementById('categoryId').value;
            const name = document.getElementById('categoryName').value;
            const desc = document.getElementById('categoryDesc').value;
            const type = categoryForm.dataset.type;

            let actionUrl = '';
            let bodyData = { name: name, description: desc };

            if (id) {
                bodyData.id = id;
                actionUrl = (type === 'category') ? 'update-category' : 'update-type';
            } else {
                actionUrl = (type === 'category') ? 'add-category' : 'add-type';
            }

            try {
                const response = await fetch(`${API_URL}/categories.php?action=${actionUrl}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + getAuthToken()
                    },
                    body: JSON.stringify(bodyData)
                });

                const result = await response.json();
                alert(result.message);
                
                if (result.success) {
                    closeCategoryModal();
                    loadCategories();
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Có lỗi xảy ra!');
            }
        });
    }
    
    // Close modals on outside click
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }
});

function showAddCategoryModal() {
    const modal = document.getElementById('categoryModal');
    const form = document.getElementById('categoryForm');
    
    document.getElementById('categoryModalTitle').textContent = 'Thêm chuyên ngành mới';
    document.getElementById('categoryId').value = '';
    document.getElementById('categoryName').value = '';
    document.getElementById('categoryDesc').value = '';
    
    form.dataset.type = 'category';
    modal.style.display = 'block';
}

function showAddTypeModal() {
    const modal = document.getElementById('categoryModal');
    const form = document.getElementById('categoryForm');
    
    document.getElementById('categoryModalTitle').textContent = 'Thêm loại tài liệu mới';
    document.getElementById('categoryId').value = '';
    document.getElementById('categoryName').value = '';
    document.getElementById('categoryDesc').value = '';
    
    form.dataset.type = 'doctype';
    modal.style.display = 'block';
}

function closeCategoryModal() {
    document.getElementById('categoryModal').style.display = 'none';
}

function editCategory(id, name, desc) {
    const modal = document.getElementById('categoryModal');
    const form = document.getElementById('categoryForm');
    
    document.getElementById('categoryModalTitle').textContent = 'Sửa chuyên ngành';
    document.getElementById('categoryId').value = id;
    document.getElementById('categoryName').value = name;
    document.getElementById('categoryDesc').value = desc;
    
    form.dataset.type = 'category';
    modal.style.display = 'block';
}

function editType(id, name, desc) {
    const modal = document.getElementById('categoryModal');
    const form = document.getElementById('categoryForm');
    
    document.getElementById('categoryModalTitle').textContent = 'Sửa loại tài liệu';
    document.getElementById('categoryId').value = id;
    document.getElementById('categoryName').value = name;
    document.getElementById('categoryDesc').value = desc;
    
    form.dataset.type = 'doctype';
    modal.style.display = 'block';
}

async function deleteCategory(id) {
    if (!confirm('Bạn có chắc chắn muốn xóa chuyên ngành này?')) return;

    try {
        const response = await fetch(`${API_URL}/categories.php?action=delete-category`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + getAuthToken()
            },
            body: JSON.stringify({ id: id })
        });
        
        const result = await response.json();
        alert(result.message);
        if (result.success) loadCategories();
    } catch (error) {
        console.error(error);
        alert('Có lỗi xảy ra!');
    }
}

async function deleteType(id) {
    if (!confirm('Xóa loại tài liệu này?')) return;

    try {
        const response = await fetch(`${API_URL}/categories.php?action=delete-type`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + getAuthToken()
            },
            body: JSON.stringify({ id: id })
        });
        
        const result = await response.json();
         alert(result.message);
        if (result.success) loadCategories();
    } catch (error) {
        console.error(error);
        alert('Có lỗi xảy ra khi xóa loại tài liệu!');
    }
}