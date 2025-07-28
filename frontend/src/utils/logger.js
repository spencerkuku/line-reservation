/**
 * 前端日誌服務
 * 提供統一的日誌記錄、錯誤追蹤和性能監控功能
 */

const isDev = import.meta.env.DEV
const isProd = import.meta.env.PROD

class LoggingService {
    constructor() {
        this.apiUrl = '/api/logs'; // 後端日誌接收端點
        this.enabled = isDev || this.isDebugMode();
        this.sessionId = this.generateSessionId();
        this.userId = null;
        this.buffer = [];
        this.bufferSize = 10; // 批量發送的緩衝區大小
        this.flushInterval = 30000; // 30秒自動刷新緩衝區
        
        this.init();
    }

    init() {
        // 設置定時刷新緩衝區
        setInterval(() => {
            this.flush();
        }, this.flushInterval);

        // 頁面卸載時發送剩餘日誌
        window.addEventListener('beforeunload', () => {
            this.flush(true);
        });

        // 監聽未處理的錯誤
        window.addEventListener('error', (event) => {
            this.logError('javascript_error', event.error, {
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno,
                message: event.message
            });
        });

        // 監聽未處理的 Promise 拒絕
        window.addEventListener('unhandledrejection', (event) => {
            this.logError('unhandled_promise_rejection', event.reason, {
                promise: event.promise
            });
        });

        console.log('LoggingService initialized', { sessionId: this.sessionId });
    }

    generateSessionId() {
        return 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    generateRequestId() {
        return 'req_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    isDebugMode() {
        return localStorage.getItem('debug_mode') === 'true' || 
               new URLSearchParams(window.location.search).get('debug') === 'true';
    }

    setUserId(userId) {
        this.userId = userId;
    }

    /**
     * 記錄一般信息
     */
    logInfo(message, data = {}, category = 'general') {
        this.log('info', message, data, category);
    }

    /**
     * 記錄警告
     */
    logWarning(message, data = {}, category = 'general') {
        this.log('warning', message, data, category);
        if (isDev) console.warn(`[${category}] ${message}`, data);
    }

    /**
     * 記錄錯誤
     */
    logError(message, error = null, data = {}, category = 'error') {
        const errorData = {
            ...data,
            error_message: error?.message || error || 'Unknown error',
            error_stack: error?.stack || null,
            error_name: error?.name || null
        };
        
        this.log('error', message, errorData, category);
        console.error(`[${category}] ${message}`, error, data);
    }

    /**
     * 記錄用戶操作
     */
    logUserAction(action, data = {}) {
        this.log('info', `User Action: ${action}`, {
            action,
            ...data,
            page: window.location.pathname,
            referrer: document.referrer
        }, 'user_action');
    }

    /**
     * 記錄頁面訪問
     */
    logPageView(pageName, data = {}) {
        this.log('info', `Page View: ${pageName}`, {
            page_name: pageName,
            url: window.location.href,
            referrer: document.referrer,
            user_agent: navigator.userAgent,
            screen_resolution: `${screen.width}x${screen.height}`,
            viewport_size: `${window.innerWidth}x${window.innerHeight}`,
            ...data
        }, 'page_view');
    }

    /**
     * 記錄 API 請求
     */
    logApiRequest(method, url, requestData = null, requestId = null) {
        this.log('info', `API Request: ${method} ${url}`, {
            method,
            url,
            request_data: this.sanitizeData(requestData),
            request_id: requestId || this.generateRequestId(),
            timestamp: new Date().toISOString()
        }, 'api_request');
    }

    /**
     * 記錄 API 響應
     */
    logApiResponse(method, url, statusCode, responseData = null, requestId = null, duration = null) {
        const level = statusCode >= 400 ? 'error' : 'info';
        this.log(level, `API Response: ${method} ${url} (${statusCode})`, {
            method,
            url,
            status_code: statusCode,
            response_data: statusCode >= 400 ? responseData : this.sanitizeData(responseData),
            request_id: requestId,
            duration_ms: duration,
            timestamp: new Date().toISOString()
        }, 'api_response');
    }

    /**
     * 記錄性能數據
     */
    logPerformance(operation, startTime, data = {}) {
        const duration = performance.now() - startTime;
        this.log('info', `Performance: ${operation}`, {
            operation,
            duration_ms: Math.round(duration),
            memory_used: performance.memory ? performance.memory.usedJSHeapSize : null,
            ...data
        }, 'performance');
    }

    /**
     * 記錄組件生命週期
     */
    logComponentLifecycle(componentName, lifecycle, data = {}) {
        this.log('info', `Component ${lifecycle}: ${componentName}`, {
            component_name: componentName,
            lifecycle,
            ...data
        }, 'component');
    }

    /**
     * 基礎日誌方法
     */
    log(level, message, data = {}, category = 'general') {
        const logEntry = {
            level,
            message,
            category,
            data: this.sanitizeData(data),
            session_id: this.sessionId,
            user_id: this.userId,
            timestamp: new Date().toISOString(),
            url: window.location.href,
            user_agent: navigator.userAgent,
            memory_usage: performance.memory ? performance.memory.usedJSHeapSize : null
        };

        // 開發模式下同時輸出到控制台
        if (this.enabled) {
            const consoleMethod = level === 'error' ? 'error' : level === 'warning' ? 'warn' : 'log';
            console[consoleMethod](`[${category}] ${message}`, data);
        }

        // 添加到緩衝區
        this.buffer.push(logEntry);

        // 如果是錯誤或緩衝區滿了，立即發送
        if (level === 'error' || this.buffer.length >= this.bufferSize) {
            this.flush();
        }
    }

    /**
     * 發送日誌到後端
     */
    async flush(isBeforeUnload = false) {
        if (this.buffer.length === 0) {
            return;
        }

        const logsToSend = [...this.buffer];
        this.buffer = [];

        try {
            const requestOptions = {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    logs: logsToSend,
                    session_id: this.sessionId
                })
            };

            if (isBeforeUnload && navigator.sendBeacon) {
                // 使用 sendBeacon 確保頁面卸載時日誌能發送
                navigator.sendBeacon(
                    this.apiUrl,
                    new Blob([requestOptions.body], { type: 'application/json' })
                );
            } else {
                const response = await fetch(this.apiUrl, requestOptions);
                if (!response.ok) {
                    console.warn('Failed to send logs to server:', response.status);
                }
            }
        } catch (error) {
            console.warn('Failed to send logs:', error);
            // 如果發送失敗，將日誌放回緩衝區（除非是頁面卸載）
            if (!isBeforeUnload) {
                this.buffer.unshift(...logsToSend);
            }
        }
    }

    /**
     * 清理敏感數據
     */
    sanitizeData(data) {
        if (!data || typeof data !== 'object') {
            return data;
        }

        const sensitiveKeys = ['password', 'token', 'api_key', 'secret', 'authorization'];
        const sanitized = {};

        for (const [key, value] of Object.entries(data)) {
            if (sensitiveKeys.some(sensitive => key.toLowerCase().includes(sensitive))) {
                sanitized[key] = '[REDACTED]';
            } else if (typeof value === 'object' && value !== null) {
                sanitized[key] = this.sanitizeData(value);
            } else {
                sanitized[key] = value;
            }
        }

        return sanitized;
    }

    /**
     * 開啟調試模式
     */
    enableDebug() {
        localStorage.setItem('debug_mode', 'true');
        this.enabled = true;
        console.log('Debug mode enabled');
    }

    /**
     * 關閉調試模式
     */
    disableDebug() {
        localStorage.removeItem('debug_mode');
        this.enabled = isDev;
        console.log('Debug mode disabled');
    }

    // 向後兼容的靜態方法
    static log(...args) {
        if (isDev) {
            console.log(...args)
        }
    }

    static warn(...args) {
        if (isDev) {
            console.warn(...args)
        }
    }

    static error(...args) {
        console.error(...args)
    }

    static info(...args) {
        if (isDev) {
            console.info(...args)
        }
    }

    static debug(...args) {
        if (isDev) {
            console.debug(...args)
        }
    }

    static table(data) {
        if (isDev && console.table) {
            console.table(data)
        }
    }

    static group(label) {
        if (isDev && console.group) {
            console.group(label)
        }
    }

    static groupEnd() {
        if (isDev && console.groupEnd) {
            console.groupEnd()
        }
    }

    static time(label) {
        if (isDev && console.time) {
            console.time(label)
        }
    }

    static timeEnd(label) {
        if (isDev && console.timeEnd) {
            console.timeEnd(label)
        }
    }

    static trackError(error, context = {}) {
        if (isProd) {
            const errorData = {
                message: error.message,
                stack: error.stack,
                timestamp: new Date().toISOString(),
                userAgent: navigator.userAgent,
                url: window.location.href,
                context
            }
            
            console.error('Production Error:', errorData)
        } else {
            console.error('Dev Error:', error, context)
        }
    }

    static apiRequest(method, url, data = null) {
        if (isDev) {
            console.group(`🔄 API ${method.toUpperCase()} ${url}`)
            if (data) {
                console.log('Request Data:', data)
            }
            console.groupEnd()
        }
    }

    static apiResponse(method, url, response, duration = null) {
        if (isDev) {
            const status = response.status || response.success ? '✅' : '❌'
            console.group(`${status} API ${method.toUpperCase()} ${url}${duration ? ` (${duration}ms)` : ''}`)
            console.log('Response:', response)
            console.groupEnd()
        }
    }

    static apiError(method, url, error) {
        if (isDev) {
            console.group(`❌ API ${method.toUpperCase()} ${url} - ERROR`)
            console.error('Error:', error)
            console.groupEnd()
        }
        
        if (isProd) {
            this.trackError(error, { method, url, type: 'API_ERROR' })
        }
    }
}

// 創建全局實例
const logger = new LoggingService();

// 導出類和實例
export default logger;
export { LoggingService };
