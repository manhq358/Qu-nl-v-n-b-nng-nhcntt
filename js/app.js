// API Base URL
const API_URL = 'api';

// State
let currentPage = 1;
let filters = {
    search: '',
    category: '',
    doc_type: '',
    format: [],
    time_range: ''
};

// Init app
document.addEventListener('DOMContentLoaded', function() {
    // Lưu ý: Hàm checkAuth() có thể nằm ở file auth.js, hãy đảm bảo file đó đã được load
    if (typeof checkAuth === 'function') {
        checkAuth();
    }
    loadCategories();
    loadDocumentTypes();
    loadDocuments();
    
    // Event listeners
    document.getElementById('searchInput').addEventListener('input', debounce(handleSearch, 500));
    document.getElementById('resetFilters').addEventListener('click', resetFilters);
    document.getElementById('timeRange').addEventListener('change', handleTimeRangeChange);
    
    // Format checkboxes
    document.querySelectorAll('input[name="format"]').forEach(cb => {
        cb.addEventListener('change', handleFormatChange);
    });
});

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Load categories
async function loadCategories() {
    try {
        const response = await fetch(`${API_URL}/categories.php?action=list-categories`);
        const result = await response.json();
        
        if (result.success) {
            const container = document.getElementById('categoryFilters');
            container.innerHTML = result.data.map(cat => `
                <label>
                    <input type="checkbox" name="category" value="${cat.id}">
                    ${cat.name}
                </label>
            `).join('');
            
            // Add event listeners
            document.querySelectorAll('input[name="category"]').forEach(cb => {
                cb.addEventListener('change', handleCategoryChange);
            });
        }
    } catch (error) {
        console.error('Error loading categories:', error);
    }
}

// Load document types
async function loadDocumentTypes() {
    try {
        const response = await fetch(`${API_URL}/categories.php?action=list-types`);
        const result = await response.json();
        
        if (result.success) {
            const container = document.getElementById('typeFilters');
            container.innerHTML = result.data.map(type => `
                <label>
                    <input type="checkbox" name="doc_type" value="${type.id}">
                    ${type.name}
                </label>
            `).join('');
            
            // Add event listeners
            document.querySelectorAll('input[name="doc_type"]').forEach(cb => {
                cb.addEventListener('change', handleDocTypeChange);
            });
        }
    } catch (error) {
        console.error('Error loading document types:', error);
    }
}

// Load documents
async function loadDocuments() {
    try {
        showLoading();
        
        const params = new URLSearchParams({
            action: 'list',
            page: currentPage,
            limit: 12,
            ...filters,
            format: filters.format.join(',')
        });
        
        const response = await fetch(`${API_URL}/documents.php?${params}`);
        const result = await response.json();
        
        if (result.success) {
            displayDocuments(result.data);
            displayPagination(result.pagination);
            displayResultCount(result.pagination.total);
        }
    } catch (error) {
        console.error('Error loading documents:', error);
        showError('Không thể tải danh sách tài liệu');
    }
}

// --- PHẦN ĐÃ SỬA: Hiển thị dạng thẻ ngang (Khớp với CSS mới) ---
function displayDocuments(documents) {
    const container = document.getElementById('documentsList');
    
    if (documents.length === 0) {
        container.innerHTML = '<p class="no-results">Không tìm thấy tài liệu nào</p>';
        return;
    }
    
    container.innerHTML = documents.map(doc => {
        // Icon theo định dạng
        let fileIcon = '📄';
        const fmt = doc.file_format ? doc.file_format.toLowerCase() : '';
        if (fmt === 'pdf') fileIcon = '📕';
        else if (fmt === 'docx' || fmt === 'doc') fileIcon = '📘';
        else if (fmt === 'pptx' || fmt === 'ppt') fileIcon = '📙';
        else if (fmt === 'zip' || fmt === 'rar') fileIcon = '📦';

        return `
        <div class="document-card" onclick="viewDocument(${doc.id})">
            <div class="card-thumbnail">
                <div style="font-size: 40px;">${fileIcon}</div>
                <div class="view-badge">👁️ ${doc.view_count}</div>
            </div>

            <div class="card-content">
                <div>
                    <h3>${highlightSearch(doc.title)}</h3>
                    <div class="card-author">
                        Tác giả: <span>${highlightSearch(doc.author_name || 'N/A')}</span>
                    </div>
                    <div class="card-subject">
                        ${doc.category_name} • ${doc.doc_type_name}
                    </div>
                </div>

                <div class="card-footer">
                    <div class="card-stats-inline">
                        <span class="format-badge">${doc.file_format.toUpperCase()}</span>
                        <span>📅 ${formatDate(doc.created_at)}</span>
                        <span>⬇️ ${doc.download_count} tải</span>
                    </div>
                </div>
            </div>
        </div>
    `}).join('');
}
// -------------------------------------------------------------

// Display pagination
function displayPagination(pagination) {
    const container = document.getElementById('pagination');
    const { page, pages } = pagination;
    
    let html = '';
    
    // Previous button
    html += `<button onclick="changePage(${page - 1})" ${page === 1 ? 'disabled' : ''}>« Trước</button>`;
    
    // Page numbers
    for (let i = 1; i <= pages; i++) {
        if (i === 1 || i === pages || (i >= page - 2 && i <= page + 2)) {
            html += `<button onclick="changePage(${i})" class="${i === page ? 'active' : ''}">${i}</button>`;
        } else if (i === page - 3 || i === page + 3) {
            html += '<span>...</span>';
        }
    }
    
    // Next button
    html += `<button onclick="changePage(${page + 1})" ${page === pages ? 'disabled' : ''}>Sau »</button>`;
    
    container.innerHTML = html;
}

// Change page
function changePage(page) {
    currentPage = page;
    loadDocuments();
    window.scrollTo(0, 0);
}

// Display result count
function displayResultCount(total) {
    document.getElementById('resultCount').textContent = `${total} kết quả`;
}

// Search handler
function handleSearch(e) {
    filters.search = e.target.value;
    currentPage = 1;
    loadDocuments();
}

// Category filter handler
function handleCategoryChange() {
    const checked = Array.from(document.querySelectorAll('input[name="category"]:checked'))
        .map(cb => cb.value);
    filters.category = checked.length > 0 ? checked[0] : ''; // Single select for simplicity
    currentPage = 1;
    loadDocuments();
}

// Doc type filter handler
function handleDocTypeChange() {
    const checked = Array.from(document.querySelectorAll('input[name="doc_type"]:checked'))
        .map(cb => cb.value);
    filters.doc_type = checked.length > 0 ? checked[0] : '';
    currentPage = 1;
    loadDocuments();
}

// Format filter handler
function handleFormatChange() {
    filters.format = Array.from(document.querySelectorAll('input[name="format"]:checked'))
        .map(cb => cb.value);
    currentPage = 1;
    loadDocuments();
}

// Time range handler
function handleTimeRangeChange(e) {
    filters.time_range = e.target.value;
    currentPage = 1;
    loadDocuments();
}

// Reset filters
function resetFilters() {
    filters = {
        search: '',
        category: '',
        doc_type: '',
        format: [],
        time_range: ''
    };
    currentPage = 1;
    
    document.getElementById('searchInput').value = '';
    document.getElementById('timeRange').value = '';
    document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
    
    loadDocuments();
}

// View document detail
function viewDocument(id) {
    window.location.href = `document-detail.html?id=${id}`;
}

// Highlight search terms
function highlightSearch(text) {
    if (!text) return '';
    if (!filters.search) return text;
    
    const searchTerm = filters.search.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(`(${searchTerm})`, 'gi');
    return text.replace(regex, '<mark>$1</mark>');
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('vi-VN');
}

// Show loading
function showLoading() {
    document.getElementById('documentsList').innerHTML = `
        <div class="loading">
            <div class="spinner"></div>
            <p>Đang tải...</p>
        </div>
    `;
}

// Show error
function showError(message) {
    document.getElementById('documentsList').innerHTML = `
        <div class="alert alert-error">${message}</div>
    `;
}

// Modal functions
function showLogin() {
    document.getElementById('loginModal').style.display = 'block';
}

function showRegister() {
    document.getElementById('registerModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}