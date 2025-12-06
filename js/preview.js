/**
 * Document Preview - Hỗ trợ PDF & Word
 * File: js/preview.js
 */

// Load Mammoth.js từ CDN cho Word preview
const mammothScript = document.createElement('script');
mammothScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js';
document.head.appendChild(mammothScript);

// CSS cho preview
const previewStyles = document.createElement('style');
previewStyles.textContent = `
    .preview-controls {
        background: white;
        padding: 16px;
        border-radius: 12px 12px 0 0;
        display: flex;
        gap: 12px;
        align-items: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        flex-wrap: wrap;
        margin-top: 20px;
    }
    
    .preview-btn {
        padding: 10px 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
        font-size: 14px;
    }
    
    .preview-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    
    .preview-info {
        padding: 8px 16px;
        background: #f3f4f6;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
    }
    
    .preview-viewer {
        background: white;
        padding: 24px;
        border-radius: 0 0 12px 12px;
        min-height: 500px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .pdf-container iframe {
        width: 100%;
        height: 700px;
        border: none;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    
    .word-viewer {
        max-width: 900px;
        margin: 0 auto;
    }
    
    .word-content {
        background: white;
        padding: 40px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        border-radius: 8px;
        line-height: 1.8;
        font-size: 16px;
        font-family: 'Times New Roman', serif;
    }
    
    .word-content h1, .word-content h2, .word-content h3 {
        margin-top: 24px;
        margin-bottom: 16px;
        color: #1f2937;
    }
    
    .word-content p {
        margin-bottom: 16px;
        text-align: justify;
    }
    
    .word-content img {
        max-width: 100%;
        height: auto;
        display: block;
        margin: 20px auto;
    }
    
    .word-content table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
    }
    
    .word-content table td, .word-content table th {
        border: 1px solid #ddd;
        padding: 8px;
    }
    
    .word-warnings {
        margin-top: 20px;
        padding: 16px;
        background: #fef3c7;
        border-left: 4px solid #f59e0b;
        border-radius: 8px;
    }
    
    .image-viewer {
        text-align: center;
        padding: 40px;
    }
    
    .preview-fallback, .preview-error {
        text-align: center;
        padding: 80px 20px;
    }
    
    .preview-fallback h3, .preview-error h3 {
        font-size: 24px;
        margin-bottom: 16px;
        color: #667eea;
    }
    
    .loading-preview {
        text-align: center;
        padding: 60px 20px;
    }
    
    .loading-preview .spinner {
        border: 4px solid #f3f3f3;
        border-top: 4px solid #667eea;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        animation: spin 1s linear infinite;
        margin: 0 auto 20px;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(previewStyles);

// Preview Document với controls đầy đủ
async function previewDocumentAdvanced(filePath, fileFormat, fileName) {
    const container = document.getElementById('previewContainer');
    
    if (!container) {
        console.error('Preview container not found');
        alert('Lỗi: Không tìm thấy vùng hiển thị preview');
        return;
    }
    
    // Clear previous content
    container.innerHTML = '';
    
    // Tạo controls bar
    const controlsHTML = `
        <div class="preview-controls">
            <button onclick="closePreview()" class="preview-btn">❌ Đóng</button>
            <button onclick="downloadCurrentFile()" class="preview-btn">⬇️ Tải xuống</button>
            ${fileFormat === 'pdf' ? `
                <button onclick="zoomIn()" class="preview-btn">🔍 Phóng to</button>
                <button onclick="zoomOut()" class="preview-btn">🔎 Thu nhỏ</button>
                <span class="preview-info">Zoom: <span id="zoomLevel">100</span>%</span>
            ` : ''}
        </div>
    `;
    
    container.innerHTML = controlsHTML;
    
    // Tạo viewer area
    const viewerDiv = document.createElement('div');
    viewerDiv.className = 'preview-viewer';
    viewerDiv.id = 'previewViewer';
    container.appendChild(viewerDiv);
    
    // Store file info for download
    window.currentPreviewFile = {
        path: filePath,
        name: fileName
    };
    
    // Load nội dung theo format
    const format = fileFormat.toLowerCase();
    
    if (format === 'pdf') {
        renderPDF(filePath, viewerDiv);
    } else if (format === 'docx' || format === 'doc') {
        await renderWord(filePath, viewerDiv);
    } else if (['jpg', 'jpeg', 'png', 'gif'].includes(format)) {
        renderImage(filePath, viewerDiv);
    } else {
        viewerDiv.innerHTML = `
            <div class="preview-fallback">
                <h3>⚠️ Không hỗ trợ xem trước định dạng ${fileFormat.toUpperCase()}</h3>
                <p>Vui lòng tải xuống để xem nội dung</p>
                <button onclick="downloadCurrentFile()" class="btn-primary">⬇️ Tải xuống</button>
            </div>
        `;
    }
    
    // Show container
    container.style.display = 'block';
    
    // Scroll to preview
    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Render PDF với zoom support
function renderPDF(filePath, container) {
    container.innerHTML = `
        <div class="pdf-container">
            <iframe 
                id="pdfFrame" 
                src="${filePath}" 
                style="width: 100%; height: 700px; border: none; border-radius: 12px;"
            ></iframe>
        </div>
    `;
    
    window.currentZoom = 100;
}

// Render Word document
async function renderWord(filePath, container) {
    container.innerHTML = `
        <div class="loading-preview">
            <div class="spinner"></div>
            <p>Đang tải Word document...</p>
        </div>
    `;
    
    try {
        // Wait for Mammoth to load
        await waitForMammoth();
        
        // Fetch file
        const response = await fetch(filePath);
        if (!response.ok) {
            throw new Error('Không thể tải file');
        }
        
        const arrayBuffer = await response.arrayBuffer();
        
        // Convert to HTML
        const result = await mammoth.convertToHtml({arrayBuffer: arrayBuffer});
        
        container.innerHTML = `
            <div class="word-viewer">
                <div class="word-content">
                    ${result.value}
                </div>
                ${result.messages.length > 0 ? `
                    <div class="word-warnings">
                        <h4>⚠️ Cảnh báo:</h4>
                        <ul>${result.messages.map(m => `<li>${m.message}</li>`).join('')}</ul>
                    </div>
                ` : ''}
            </div>
        `;
    } catch (error) {
        console.error('Error rendering Word:', error);
        container.innerHTML = `
            <div class="preview-error">
                <h3>❌ Không thể hiển thị Word document</h3>
                <p>${error.message}</p>
                <p>File có thể bị lỗi hoặc định dạng không được hỗ trợ.</p>
                <button onclick="downloadCurrentFile()" class="btn-primary">⬇️ Tải xuống để xem</button>
            </div>
        `;
    }
}

// Render Image
function renderImage(filePath, container) {
    container.innerHTML = `
        <div class="image-viewer">
            <img src="${filePath}" alt="Image preview" style="max-width: 100%; height: auto; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        </div>
    `;
}

// Wait for Mammoth to load
function waitForMammoth() {
    return new Promise((resolve, reject) => {
        let attempts = 0;
        const maxAttempts = 100; // 10 seconds
        
        const checkMammoth = setInterval(() => {
            attempts++;
            
            if (typeof mammoth !== 'undefined') {
                clearInterval(checkMammoth);
                resolve();
            }
            
            if (attempts >= maxAttempts) {
                clearInterval(checkMammoth);
                reject(new Error('Mammoth.js không thể tải. Kiểm tra kết nối internet.'));
            }
        }, 100);
    });
}

// Zoom functions cho PDF
function zoomIn() {
    if (window.currentZoom < 200) {
        window.currentZoom += 10;
        updateZoom();
    }
}

function zoomOut() {
    if (window.currentZoom > 50) {
        window.currentZoom -= 10;
        updateZoom();
    }
}

function updateZoom() {
    const frame = document.getElementById('pdfFrame');
    if (frame) {
        frame.style.transform = `scale(${window.currentZoom / 100})`;
        frame.style.transformOrigin = 'top center';
        frame.style.height = (700 * window.currentZoom / 100) + 'px';
    }
    
    const zoomLevel = document.getElementById('zoomLevel');
    if (zoomLevel) {
        zoomLevel.textContent = window.currentZoom;
    }
}

// Close preview
function closePreview() {
    const container = document.getElementById('previewContainer');
    if (container) {
        container.style.display = 'none';
        container.innerHTML = '';
    }
}

// Download current file
function downloadCurrentFile() {
    if (window.currentPreviewFile) {
        const link = document.createElement('a');
        link.href = window.currentPreviewFile.path;
        link.download = window.currentPreviewFile.name;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        alert('✅ Đang tải xuống...');
    } else {
        alert('❌ Không tìm thấy thông tin file');
    }
}

console.log('✅ Preview.js loaded successfully');