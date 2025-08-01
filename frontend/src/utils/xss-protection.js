/**
 * XSS Protection Utilities
 * 前端 XSS 防護工具
 */

/**
 * HTML 實體編碼
 * @param {string} text - 要編碼的文字
 * @returns {string} 編碼後的安全文字
 */
export function escapeHtml(text) {
    if (typeof text !== 'string') {
        return text;
    }
    
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * 移除所有 HTML 標籤
 * @param {string} html - 包含 HTML 的字串
 * @returns {string} 純文字
 */
export function stripHtml(html) {
    if (typeof html !== 'string') {
        return html;
    }
    
    const div = document.createElement('div');
    div.innerHTML = html;
    return div.textContent || div.innerText || '';
}

/**
 * 清理用戶輸入，防止 XSS
 * @param {string} input - 用戶輸入
 * @returns {string} 清理後的安全輸入
 */
export function sanitizeInput(input) {
    if (typeof input !== 'string') {
        return input;
    }
    
    // 移除潛在的惡意字符
    return input
        .replace(/<script[^>]*>.*?<\/script>/gi, '') // 移除 script 標籤
        .replace(/<iframe[^>]*>.*?<\/iframe>/gi, '') // 移除 iframe 標籤
        .replace(/<object[^>]*>.*?<\/object>/gi, '') // 移除 object 標籤
        .replace(/<embed[^>]*>/gi, '') // 移除 embed 標籤
        .replace(/javascript:/gi, '') // 移除 javascript: 協議
        .replace(/vbscript:/gi, '') // 移除 vbscript: 協議
        .replace(/on\w+\s*=/gi, '') // 移除事件處理器
        .trim();
}

/**
 * 驗證並清理電子郵件
 * @param {string} email - 電子郵件地址
 * @returns {string|null} 清理後的電子郵件或 null
 */
export function sanitizeEmail(email) {
    if (typeof email !== 'string') {
        return null;
    }
    
    const cleaned = sanitizeInput(email.toLowerCase().trim());
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    
    return emailRegex.test(cleaned) ? cleaned : null;
}

/**
 * 驗證並清理電話號碼
 * @param {string} phone - 電話號碼
 * @returns {string|null} 清理後的電話號碼或 null
 */
export function sanitizePhone(phone) {
    if (typeof phone !== 'string') {
        return null;
    }
    
    const cleaned = sanitizeInput(phone).replace(/[^\d\-\+\(\)\s]/g, '');
    const phoneRegex = /^[\+]?[\d\-\(\)\s]{8,20}$/;
    
    return phoneRegex.test(cleaned) ? cleaned : null;
}

/**
 * 驗證並清理姓名
 * @param {string} name - 姓名
 * @returns {string|null} 清理後的姓名或 null
 */
export function sanitizeName(name) {
    if (typeof name !== 'string') {
        return null;
    }
    
    const cleaned = sanitizeInput(name).replace(/[<>\"']/g, '');
    
    // 姓名應該只包含字母、數字、空格和一些常見符號
    const nameRegex = /^[\u4e00-\u9fa5a-zA-Z0-9\s\-\.]{1,50}$/;
    
    return nameRegex.test(cleaned) ? cleaned.trim() : null;
}

/**
 * 驗證並清理備註
 * @param {string} note - 備註內容
 * @returns {string|null} 清理後的備註或 null
 */
export function sanitizeNote(note) {
    if (typeof note !== 'string') {
        return null;
    }
    
    const cleaned = sanitizeInput(note);
    
    // 備註長度限制
    if (cleaned.length > 500) {
        return cleaned.substring(0, 500);
    }
    
    return cleaned;
}

/**
 * 安全地設置 innerHTML
 * @param {HTMLElement} element - DOM 元素
 * @param {string} html - HTML 內容
 */
export function safeSetInnerHTML(element, html) {
    if (!element || typeof html !== 'string') {
        return;
    }
    
    // 清理 HTML 內容
    const cleanedHtml = sanitizeInput(html);
    element.innerHTML = cleanedHtml;
}

/**
 * 創建安全的 URL
 * @param {string} url - URL 字串
 * @returns {string|null} 安全的 URL 或 null
 */
export function sanitizeUrl(url) {
    if (typeof url !== 'string') {
        return null;
    }
    
    const cleaned = sanitizeInput(url);
    
    // 只允許 http、https 和相對路徑
    if (cleaned.startsWith('http://') || cleaned.startsWith('https://') || cleaned.startsWith('/')) {
        return cleaned;
    }
    
    return null;
}

/**
 * 生成安全的隨機 ID
 * @returns {string} 隨機 ID
 */
export function generateSecureId() {
    return crypto.randomUUID ? crypto.randomUUID() : 
           'id-' + Math.random().toString(36).substr(2, 9) + '-' + Date.now().toString(36);
}

/**
 * XSS 防護配置
 */
export const XSSConfig = {
    // 允許的 HTML 標籤（用於富文本編輯器等場景）
    allowedTags: ['b', 'i', 'u', 'strong', 'em', 'br', 'p'],
    
    // 允許的屬性
    allowedAttributes: ['class'],
    
    // 最大輸入長度
    maxInputLength: {
        name: 50,
        email: 100,
        phone: 20,
        note: 500,
        general: 255
    }
};

/**
 * 根據配置清理 HTML
 * @param {string} html - HTML 內容
 * @param {Object} config - 清理配置
 * @returns {string} 清理後的 HTML
 */
export function sanitizeHtmlWithConfig(html, config = XSSConfig) {
    if (typeof html !== 'string') {
        return '';
    }
    
    const div = document.createElement('div');
    div.innerHTML = html;
    
    // 移除不允許的標籤
    const allElements = div.querySelectorAll('*');
    allElements.forEach(element => {
        if (!config.allowedTags.includes(element.tagName.toLowerCase())) {
            element.replaceWith(...element.childNodes);
        } else {
            // 移除不允許的屬性
            Array.from(element.attributes).forEach(attr => {
                if (!config.allowedAttributes.includes(attr.name)) {
                    element.removeAttribute(attr.name);
                }
            });
        }
    });
    
    return div.innerHTML;
}
