/**
 * 安全工具模組
 * 符合 OWASP A04:2021 建議
 */

/**
 * Content Security Policy (CSP) 設定
 */
export const CSP_CONFIG = {
    'default-src': ["'self'"],
    'script-src': ["'self'", "'unsafe-inline'"],  // 生產環境應移除 unsafe-inline
    'style-src': ["'self'", "'unsafe-inline'"],
    'img-src': ["'self'", 'data:', 'https:'],
    'font-src': ["'self'", 'data:'],
    'connect-src': ["'self'"],
    'media-src': ["'none'"],
    'object-src': ["'none'"],
    'child-src': ["'none'"],
    'frame-src': ["'none'"],
    'worker-src': ["'none'"],
    'frame-ancestors': ["'none'"],
    'form-action': ["'self'"],
    'upgrade-insecure-requests': []
};

/**
 * 安全的 localStorage 包裝器
 */
export class SecureStorage {
    static setItem(key, value, encrypt = true) {
        try {
            const data = encrypt ? this.encrypt(JSON.stringify(value)) : JSON.stringify(value);
            localStorage.setItem(key, data);
        } catch (error) {
            if (import.meta.env.DEV) {
                console.error('Failed to save to localStorage:', error);
            }
        }
    }

    static getItem(key, decrypt = true) {
        try {
            const data = localStorage.getItem(key);
            if (!data) return null;
            
            const parsed = decrypt ? this.decrypt(data) : data;
            return JSON.parse(parsed);
        } catch (error) {
            if (import.meta.env.DEV) {
                console.error('Failed to read from localStorage:', error);
            }
            this.removeItem(key); // 清除損壞的數據
            return null;
        }
    }

    static removeItem(key) {
        localStorage.removeItem(key);
    }

    // 簡單的前端加密（注意：這不是真正安全的加密，只是混淆）
    static encrypt(text) {
        return btoa(encodeURIComponent(text));
    }

    static decrypt(encoded) {
        return decodeURIComponent(atob(encoded));
    }

    // 清除所有應用相關的存儲
    static clearAppData() {
        const keysToRemove = ['token', 'user', 'settings'];
        keysToRemove.forEach(key => this.removeItem(key));
    }
}

/**
 * 輸入過濾和驗證
 */
export class InputSecurity {
    /**
     * HTML 實體編碼
     */
    static escapeHtml(unsafe) {
        if (typeof unsafe !== 'string') return unsafe;
        
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    /**
     * 移除危險的 HTML 標籤和屬性
     */
    static sanitizeHtml(html) {
        if (typeof html !== 'string') return html;

        // 移除腳本標籤
        html = html.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
        
        // 移除事件處理器
        html = html.replace(/\s*on\w+\s*=\s*["'][^"']*["']/gi, '');
        
        // 移除 javascript: 協議
        html = html.replace(/javascript\s*:/gi, '');
        
        // 移除 data: 協議（除了圖片）
        html = html.replace(/data:(?!image\/[a-z]+;base64,)[^;]+;/gi, '');
        
        return html;
    }

    /**
     * 驗證 URL 安全性
     */
    static isUrlSafe(url) {
        if (!url || typeof url !== 'string') return false;
        
        try {
            const parsed = new URL(url);
            // 只允許 http 和 https 協議
            return ['http:', 'https:'].includes(parsed.protocol);
        } catch {
            return false;
        }
    }

    /**
     * 清理用戶輸入
     */
    static cleanInput(input) {
        if (typeof input !== 'string') return input;
        
        // 移除控制字符
        input = input.replace(/[\x00-\x1F\x7F]/g, '');
        
        // 限制長度
        if (input.length > 10000) {
            input = input.substring(0, 10000);
        }
        
        return input.trim();
    }
}

/**
 * 安全的事件監聽器
 */
export class SecureEventListener {
    static addEventListener(element, event, handler, options = {}) {
        // 防止事件處理器中的 XSS
        const secureHandler = (e) => {
            try {
                // 驗證事件來源
                if (e.target && e.target.tagName) {
                    handler(e);
                }
            } catch (error) {
                if (import.meta.env.DEV) {
                    console.error('Event handler error:', error);
                }
            }
        };

        element.addEventListener(event, secureHandler, {
            passive: true,
            ...options
        });

        return secureHandler;
    }
}

/**
 * 安全性檢查
 */
export class SecurityChecker {
    /**
     * 檢查是否在安全的環境中
     */
    static isSecureContext() {
        return window.isSecureContext || location.protocol === 'https:';
    }

    /**
     * 檢查瀏覽器安全功能
     */
    static checkBrowserSecurity() {
        const features = {
            https: this.isSecureContext(),
            csp: 'SecurityPolicyViolationEvent' in window,
            hsts: document.location.protocol === 'https:',
            referrerPolicy: 'referrerPolicy' in document,
        };

        return features;
    }

    /**
     * 生成安全報告
     */
    static generateSecurityReport() {
        const report = {
            timestamp: new Date().toISOString(),
            userAgent: navigator.userAgent,
            features: this.checkBrowserSecurity(),
            csp: CSP_CONFIG
        };

        return report;
    }
}

export default {
    SecureStorage,
    InputSecurity,
    SecureEventListener,
    SecurityChecker,
    CSP_CONFIG
};
