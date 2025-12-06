// Check authentication status
async function checkAuth() {
    const token = localStorage.getItem('token');
    
    if (!token) {
        showAuthButtons();
        return;
    }
    
    try {
        const response = await fetch(`${API_URL}/auth.php?action=verify`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            showUserMenu(result.user);
        } else {
            localStorage.removeItem('token');
            showAuthButtons();
        }
    } catch (error) {
        console.error('Auth check failed:', error);
        showAuthButtons();
    }
}

// Show auth buttons
function showAuthButtons() {
    document.getElementById('authButtons').style.display = 'flex'; // ✅ Sửa thành flex
    document.getElementById('userMenu').style.display = 'none';
}

// Show user menu
function showUserMenu(user) {
    document.getElementById('authButtons').style.display = 'none'; // ✅ Ẩn auth buttons
    document.getElementById('userMenu').style.display = 'flex';
    document.getElementById('userName').textContent = user.full_name;
    
    // Show admin link if user is admin
    if (user.role === 'admin') {
        const adminLink = document.getElementById('adminLink');
        if (adminLink) {
            adminLink.style.display = 'inline'; // ✅ Sửa thành inline
            adminLink.href = 'admin/admin.html';
        }
    }
}

// Login form handler
document.getElementById('loginForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = {
        email: formData.get('email'),
        password: formData.get('password')
    };
    
    try {
        const response = await fetch(`${API_URL}/auth.php?action=login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            localStorage.setItem('token', result.token);
            localStorage.setItem('user', JSON.stringify(result.user));
            
            showAlert('Đăng nhập thành công!', 'success');
            closeModal('loginModal');
            
            // ✅ THÊM: Kiểm tra role và redirect
            setTimeout(() => {
                if (result.user.role === 'admin') {
                    // Nếu là admin → chuyển sang trang admin
                    window.location.href = 'admin/admin.html';
                } else {
                    // Nếu là user thường → reload trang hiện tại
                    window.location.reload();
                }
            }, 1000);
        } else {
            showAlert(result.message, 'error');
        }
    } catch (error) {
        console.error('Login error:', error);
        showAlert('Đăng nhập thất bại. Vui lòng thử lại.', 'error');
    }
});

// Register form handler
document.getElementById('registerForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = {
        email: formData.get('email'),
        password: formData.get('password'),
        full_name: formData.get('full_name'),
        student_code: formData.get('student_code'),
        role: formData.get('role'),
        department: formData.get('department')
    };
    
    // Validate password
    if (data.password.length < 8) {
        showAlert('Mật khẩu phải có ít nhất 8 ký tự', 'error');
        return;
    }
    
    if (!/[A-Z]/.test(data.password) || !/[0-9]/.test(data.password)) {
        showAlert('Mật khẩu phải có chữ hoa và số', 'error');
        return;
    }
    
    try {
        const response = await fetch(`${API_URL}/auth.php?action=register`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('Đăng ký thành công! Vui lòng đăng nhập.', 'success');
            closeModal('registerModal');
            e.target.reset();
            
            // Show login modal after 2 seconds
            setTimeout(() => {
                showLogin();
            }, 2000);
        } else {
            showAlert(result.message, 'error');
        }
    } catch (error) {
        console.error('Register error:', error);
        showAlert('Đăng ký thất bại. Vui lòng thử lại.', 'error');
    }
});

// Logout
function logout() {
    if (confirm('Bạn có chắc chắn muốn đăng xuất?')) {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        window.location.href = '../index.html'; // ✅ Sửa để logout từ admin về trang chủ
    }
}

// Show alert
function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    
    // Insert at top of modal content or body
    const modal = document.querySelector('.modal[style*="block"] .modal-content');
    if (modal) {
        modal.insertBefore(alertDiv, modal.firstChild);
    } else {
        document.body.insertBefore(alertDiv, document.body.firstChild);
    }
    
    // Remove after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Get auth token
function getAuthToken() {
    return localStorage.getItem('token');
}

// Get current user
function getCurrentUser() {
    const user = localStorage.getItem('user');
    return user ? JSON.parse(user) : null;
}

// Check if user is authenticated
function isAuthenticated() {
    return !!localStorage.getItem('token');
}

// Redirect if not authenticated
function requireAuth() {
    if (!isAuthenticated()) {
        window.location.href = '../index.html'; // ✅ Sửa đường dẫn từ admin về index
        return false;
    }
    return true;
}

// Redirect if not admin
function requireAdmin() {
    const user = getCurrentUser();
    if (!user || user.role !== 'admin') {
        alert('Bạn không có quyền truy cập trang này');
        window.location.href = '../index.html'; // ✅ Sửa đường dẫn từ admin về index
        return false;
    }
    return true;
}