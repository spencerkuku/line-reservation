import router from '../router.js'

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api'

// 輸入過濾函數 (防止XSS)
function sanitizeInput(input) {
    if (typeof input === 'string') {
        return input.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
                   .replace(/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/gi, '')
                   .replace(/javascript:/gi, '')
                   .replace(/on\w+\s*=/gi, '')
                   .replace(/data:text\/html/gi, '');
    }
    if (typeof input === 'object' && input !== null && !Array.isArray(input)) {
        const sanitized = {};
        for (const [key, value] of Object.entries(input)) {
            // 只處理安全的鍵名
            if (/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(key)) {
                sanitized[key] = sanitizeInput(value);
            }
        }
        return sanitized;
    }
    if (Array.isArray(input)) {
        return input.map(item => sanitizeInput(item));
    }
    return input;
}

// 檢查是否為有效的 token（Laravel Sanctum 使用隨機字符串）
function isValidToken(token) {
    if (!token) return false;
    // Laravel Sanctum token 是隨機字符串，長度通常在 40-80 字符之間
    return typeof token === 'string' && token.length >= 10 && token.length <= 100;
}

// 獲取 CSRF Cookie（用於 Sanctum SPA 認證）
async function getCsrfCookie() {
    try {
        const baseUrl = API_BASE_URL.replace('/api', '');
        const response = await fetch(`${baseUrl}/sanctum/csrf-cookie`, {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            }
        });
        
        if (!response.ok) {
            console.warn(`CSRF cookie request failed: ${response.status} ${response.statusText}`);
        }
        
        return response.ok;
    } catch (error) {
        console.warn('無法獲取 CSRF cookie:', error);
        return false;
    }
}

// 從 Cookie 中獲取 CSRF Token
function getCsrfTokenFromCookie() {
    const name = 'XSRF-TOKEN';
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) {
        const token = parts.pop().split(';').shift();
        return decodeURIComponent(token);
    }
    return null;
}

// 統一的 HTTP 請求函數
async function apiRequest(url, options = {}) {
    const token = localStorage.getItem('token')
    
    // 驗證URL安全性
    if (!url.startsWith('/')) {
        throw new Error('Invalid API endpoint');
    }
    
    // 驗證token格式
    if (token && !isValidToken(token)) {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        throw new Error('Invalid token format');
    }
    
    // 對於認證相關的請求，先獲取 CSRF cookie
    if (url.startsWith('/auth/') && (options.method === 'POST' || !options.method)) {
        const csrfSuccess = await getCsrfCookie();
        if (!csrfSuccess) {
            console.warn('無法獲取 CSRF cookie，可能影響認證請求');
        }
    }
    
    // 獲取 CSRF token
    const csrfToken = getCsrfTokenFromCookie();
    
    // 預設設定
    const defaultOptions = {
        credentials: 'include', // 支援cookies和CSRF
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest', // 防CSRF
            ...(token && { 'Authorization': `Bearer ${token}` }),
            ...(csrfToken && { 'X-XSRF-TOKEN': csrfToken })
        }
    }
    
    // 合併選項
    const mergedOptions = {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...options.headers
        }
    }
    
    // 如果有body且是JSON，先進行過濾
    if (mergedOptions.body && mergedOptions.headers['Content-Type'] === 'application/json') {
        try {
            const bodyData = JSON.parse(mergedOptions.body);
            mergedOptions.body = JSON.stringify(sanitizeInput(bodyData));
        } catch (e) {
            // 如果解析失敗，保持原樣
        }
    }
    
    try {
        const response = await fetch(`${API_BASE_URL}${url}`, mergedOptions)
        
        // 檢查 401 或 403 錯誤
        if (response.status === 401 || response.status === 403) {
            console.warn('Token 驗證失敗或權限不足，重定向到登入頁面')
            
            // 清除本地存儲
            localStorage.removeItem('token')
            localStorage.removeItem('user')
            
            // 重定向到登入頁面
            if (router.currentRoute.value.name !== 'Login') {
                router.push({ name: 'Login' })
            }
            
            // 拋出錯誤
            const errorData = await response.json().catch(() => ({}))
            throw new Error(errorData.message || '認證失敗，請重新登入')
        }
        
        // 檢查速率限制
        if (response.status === 429) {
            const errorData = await response.json().catch(() => ({}))
            throw new Error(errorData.message || '請求過於頻繁，請稍後再試')
        }
        
        // 檢查其他錯誤
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}))
            throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`)
        }
        
        return response
    } catch (error) {
        // 網絡錯誤或其他錯誤
        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            throw new Error('無法連接到服務器，請檢查網絡連接')
        }
        throw error
    }
}

// GET 請求
export async function apiGet(url, params = {}) {
    // 如果有參數，將其添加到URL中
    if (Object.keys(params).length > 0) {
        const searchParams = new URLSearchParams()
        Object.entries(params).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                searchParams.append(key, value)
            }
        })
        const queryString = searchParams.toString()
        if (queryString) {
            url += (url.includes('?') ? '&' : '?') + queryString
        }
    }
    
    const response = await apiRequest(url, { method: 'GET' })
    return response.json()
}

// POST 請求
export async function apiPost(url, data) {
    const response = await apiRequest(url, {
        method: 'POST',
        body: JSON.stringify(data)
    })
    return response.json()
}

// PUT 請求
export async function apiPut(url, data) {
    const response = await apiRequest(url, {
        method: 'PUT',
        body: JSON.stringify(data)
    })
    return response.json()
}

// DELETE 請求
export async function apiDelete(url) {
    const response = await apiRequest(url, { method: 'DELETE' })
    return response.json()
}

// 檔案上傳請求
export async function apiUpload(url, formData, method = 'POST') {
    const token = localStorage.getItem('token')
    
    // 先獲取 CSRF cookie
    await getCsrfCookie();
    
    // 獲取 CSRF token
    const csrfToken = getCsrfTokenFromCookie();
    
    const response = await fetch(`${API_BASE_URL}${url}`, {
        method: method,
        credentials: 'include', // 支援cookies和CSRF
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token && { 'Authorization': `Bearer ${token}` }),
            ...(csrfToken && { 'X-XSRF-TOKEN': csrfToken })
            // 不設定 Content-Type，讓瀏覽器自動設定 multipart/form-data
        },
        body: formData
    })
    
    // 檢查 401 或 403 錯誤
    if (response.status === 401 || response.status === 403) {
        localStorage.removeItem('token')
        localStorage.removeItem('user')
        
        if (router.currentRoute.value.name !== 'Login') {
            router.push({ name: 'Login' })
        }
        
        const errorData = await response.json().catch(() => ({}))
        throw new Error(errorData.message || '認證失敗，請重新登入')
    }
    
    if (!response.ok) {
        const errorData = await response.json().catch(() => ({}))
        throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`)
    }
    
    return response.json()
}

// 檢查 token 有效性並返回用戶資料
export async function validateToken() {
    const token = localStorage.getItem('token')
    if (!token) {
        return false
    }
    
    try {
        const userData = await apiGet('/auth/user')
        
        // 更新用戶信息（防止角色變更）
        if (userData.user) {
            localStorage.setItem('user', JSON.stringify(userData.user))
        }
        
        return true
    } catch (error) {
        console.warn('Token 驗證失敗:', error.message)
        return false
    }
}

export { API_BASE_URL, getCsrfCookie, getCsrfTokenFromCookie }
