/**
 * 智能日誌系統 - 僅在調試模式下顯示詳細日誌
 * Smart Logging System - Only show detailed logs in debug mode
 * 
 * 功能特點:
 * - 純前端日誌系統，不發送任何 API 請求
 * - 自動環境檢測 (development/production)
 * - 開發模式顯示所有日誌，生產模式僅顯示 warn/error
 * - 保留日誌歷史記錄供調試使用
 */

class LoggingService {
    constructor() {
        this.debugMode = this.isDebugMode();
        this.logHistory = [];
        this.maxHistorySize = 100;
        
        // 綁定方法
        this.log = this.log.bind(this);
        this.info = this.info.bind(this);
        this.warn = this.warn.bind(this);
        this.error = this.error.bind(this);
        this.debug = this.debug.bind(this);
        this.trace = this.trace.bind(this);
        
        // 在控制台輸出當前環境信息（始終顯示）
        const envInfo = {
            debugMode: this.debugMode,
            NODE_ENV: import.meta.env?.NODE_ENV,
            VITE_NODE_ENV: import.meta.env?.VITE_NODE_ENV,
            DEV: import.meta.env?.DEV,
            PROD: import.meta.env?.PROD,
            VITE_DEBUG: import.meta.env?.VITE_DEBUG,
            mode: import.meta.env?.MODE
        };
        console.log('[Logger] Environment Info:', envInfo);
        console.log('[Logger] Pure frontend logging - No API requests will be sent');
    }

    /**
     * 檢查是否為調試模式
     * 嚴格按照環境變數判斷，確保生產模式安全
     */
    isDebugMode() {
        try {
            // 首先檢查明確的生產環境設置 - 最高優先級
            if (import.meta.env?.NODE_ENV === 'production') return false;
            if (import.meta.env?.VITE_NODE_ENV === 'production') return false;
            if (import.meta.env?.MODE === 'production') return false;
            if (import.meta.env?.PROD === true) return false;
            
            // 檢查明確的調試禁用設置
            if (import.meta.env?.VITE_DEBUG === 'false') return false;
            if (import.meta.env?.VITE_DEBUG === false) return false;
            
            // 檢查 localStorage 中的調試標誌（僅在非生產環境）
            if (typeof window !== 'undefined' && window.localStorage) {
                const debugFlag = localStorage.getItem('debug_mode');
                if (debugFlag === 'true') return true;
                if (debugFlag === 'false') return false;
            }
            
            // 檢查 URL 參數（僅在非生產環境）
            if (typeof window !== 'undefined' && window.location) {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('debug') === 'true') return true;
                if (urlParams.get('debug') === 'false') return false;
            }
            
            // 檢查環境變數中的調試設置
            if (import.meta.env?.VITE_DEBUG === 'true') return true;
            if (import.meta.env?.VITE_DEBUG === true) return true;
            
            // 檢查是否為開發環境
            if (import.meta.env?.NODE_ENV === 'development') return true;
            if (import.meta.env?.VITE_NODE_ENV === 'development') return true;
            if (import.meta.env?.MODE === 'development') return true;
            if (import.meta.env?.DEV === true) return true;
            
            // 默認返回 false (生產模式) - 安全優先
            return false;
        } catch (e) {
            // 發生錯誤時預設為生產模式（安全優先）
            console.warn('[Logger] Error detecting debug mode, defaulting to production mode');
            return false;
        }
    }

    /**
     * 檢查是否為生產模式
     */
    isProductionMode() {
        return !this.debugMode;
    }

    /**
     * 格式化日誌訊息
     */
    formatMessage(level, message, ...args) {
        const timestamp = new Date().toLocaleTimeString();
        const prefix = `[${timestamp}] [${level.toUpperCase()}]`;
        
        if (args.length > 0) {
            return { prefix, message, args };
        }
        return { prefix, message: String(message), args: [] };
    }

    /**
     * 添加到歷史記錄（總是記錄，但不發送到服務器）
     */
    addToHistory(level, message, ...args) {
        const logEntry = {
            timestamp: new Date(),
            level,
            message,
            args: args.map(arg => {
                // 安全地序列化參數，避免循環引用
                try {
                    return typeof arg === 'object' ? JSON.parse(JSON.stringify(arg)) : arg;
                } catch {
                    return String(arg);
                }
            })
        };
        
        this.logHistory.push(logEntry);
        
        // 限制歷史記錄大小
        if (this.logHistory.length > this.maxHistorySize) {
            this.logHistory.shift();
        }
    }

    /**
     * 一般日誌 - 僅在開發模式顯示
     */
    log(message, ...args) {
        this.addToHistory('log', message, ...args);
        
        if (this.debugMode) {
            const formatted = this.formatMessage('log', message, ...args);
            console.log(formatted.prefix, formatted.message, ...formatted.args);
        }
    }

    /**
     * 資訊日誌 - 僅在開發模式顯示
     */
    info(message, ...args) {
        this.addToHistory('info', message, ...args);
        
        if (this.debugMode) {
            const formatted = this.formatMessage('info', message, ...args);
            console.info(formatted.prefix, formatted.message, ...formatted.args);
        }
    }

    /**
     * 警告日誌 - 始終顯示（開發和生產模式）
     */
    warn(message, ...args) {
        this.addToHistory('warn', message, ...args);
        
        const formatted = this.formatMessage('warn', message, ...args);
        console.warn(formatted.prefix, formatted.message, ...formatted.args);
    }

    /**
     * 錯誤日誌 - 始終顯示（開發和生產模式）
     */
    error(message, ...args) {
        this.addToHistory('error', message, ...args);
        
        const formatted = this.formatMessage('error', message, ...args);
        console.error(formatted.prefix, formatted.message, ...formatted.args);
    }

    /**
     * 調試日誌 - 僅在開發模式顯示
     */
    debug(message, ...args) {
        this.addToHistory('debug', message, ...args);
        
        if (this.debugMode) {
            const formatted = this.formatMessage('debug', message, ...args);
            console.debug(formatted.prefix, formatted.message, ...formatted.args);
        }
    }

    /**
     * 追蹤日誌 - 僅在開發模式顯示
     */
    trace(message, ...args) {
        this.addToHistory('trace', message, ...args);
        
        if (this.debugMode) {
            const formatted = this.formatMessage('trace', message, ...args);
            console.trace(formatted.prefix, formatted.message, ...formatted.args);
        }
    }

    /**
     * 組日誌開始 - 僅在開發模式顯示
     */
    group(label) {
        if (this.debugMode) {
            console.group(label);
        }
    }

    /**
     * 組日誌結束 - 僅在開發模式顯示
     */
    groupEnd() {
        if (this.debugMode) {
            console.groupEnd();
        }
    }

    /**
     * 表格日誌 - 僅在開發模式顯示
     */
    table(data) {
        if (this.debugMode) {
            console.table(data);
        }
    }

    /**
     * 計時開始 - 僅在開發模式顯示
     */
    time(label) {
        if (this.debugMode) {
            console.time(label);
        }
    }

    /**
     * 計時結束 - 僅在開發模式顯示
     */
    timeEnd(label) {
        if (this.debugMode) {
            console.timeEnd(label);
        }
    }

    /**
     * 清空控制台 - 僅在開發模式執行
     */
    clear() {
        if (this.debugMode) {
            console.clear();
        }
    }

    /**
     * 獲取日誌歷史
     */
    getHistory() {
        return [...this.logHistory];
    }

    /**
     * 清空日誌歷史
     */
    clearHistory() {
        this.logHistory = [];
        if (this.debugMode) {
            console.log('[Logger] History cleared');
        }
    }

    /**
     * 設置調試模式（僅在非生產環境允許）
     */
    setDebugMode(enabled) {
        // 在生產環境中不允許啟用調試模式
        if (import.meta.env?.NODE_ENV === 'production' || 
            import.meta.env?.VITE_NODE_ENV === 'production' ||
            import.meta.env?.MODE === 'production' ||
            import.meta.env?.PROD === true) {
            console.warn('[Logger] Cannot enable debug mode in production environment');
            return;
        }
        
        this.debugMode = enabled;
        if (typeof window !== 'undefined' && window.localStorage) {
            localStorage.setItem('debug_mode', enabled ? 'true' : 'false');
        }
        
        console.log(`[Logger] Debug mode ${enabled ? 'enabled' : 'disabled'}`);
    }

    /**
     * 獲取當前調試模式狀態
     */
    getDebugMode() {
        return this.debugMode;
    }

    /**
     * 獲取當前環境信息
     */
    getEnvironmentInfo() {
        return {
            debugMode: this.debugMode,
            isProduction: this.isProductionMode(),
            NODE_ENV: import.meta.env?.NODE_ENV,
            VITE_NODE_ENV: import.meta.env?.VITE_NODE_ENV,
            MODE: import.meta.env?.MODE,
            DEV: import.meta.env?.DEV,
            PROD: import.meta.env?.PROD,
            VITE_DEBUG: import.meta.env?.VITE_DEBUG
        };
    }

    /**
     * API 請求日誌 - 純本地記錄，不發送請求
     */
    apiRequest(method, url, data = null) {
        const message = `API ${method.toUpperCase()}: ${url}`;
        this.debug(message, this.debugMode ? data : null);
    }

    /**
     * API 響應日誌 - 純本地記錄，不發送請求
     */
    apiResponse(method, url, status, data = null) {
        const message = `API ${method.toUpperCase()} ${status}: ${url}`;
        if (status >= 400) {
            this.error(message, this.debugMode ? data : null);
        } else {
            this.debug(message, this.debugMode ? data : null);
        }
    }

    /**
     * 用戶操作日誌 - 純本地記錄
     */
    userAction(action, details = null) {
        const message = `User Action: ${action}`;
        this.info(message, this.debugMode ? details : null);
    }

    /**
     * 路由變更日誌 - 純本地記錄
     */
    routeChange(from, to) {
        const message = `Route: ${from} -> ${to}`;
        this.debug(message);
    }

    /**
     * 組件生命週期日誌 - 純本地記錄
     */
    lifecycle(component, stage, data = null) {
        const message = `${component} ${stage}`;
        this.debug(message, this.debugMode ? data : null);
    }

    /**
     * 錯誤邊界日誌 - 始終記錄重要錯誤
     */
    errorBoundary(error, errorInfo) {
        this.error('Error Boundary Caught:', error, this.debugMode ? errorInfo : null);
    }

    /**
     * 性能標記 - 僅在開發模式記錄
     */
    performance(label, startTime, additionalData = null) {
        if (this.debugMode) {
            const duration = performance.now() - startTime;
            this.debug(`Performance: ${label} took ${duration.toFixed(2)}ms`, additionalData);
        }
    }

    /**
     * 安全日誌 - 敏感操作記錄（始終記錄但不包含敏感數據）
     */
    security(action, details = null) {
        // 在生產模式下移除敏感信息
        const safeDetails = this.debugMode ? details : { action: action, timestamp: new Date().toISOString() };
        this.warn(`Security: ${action}`, safeDetails);
    }
}

// 創建全局實例
const logger = new LoggingService();

// 導出實例和類
export default logger;
export { LoggingService };

// 如果在瀏覽器環境中，將 logger 掛載到 window 對象上以便調試
if (typeof window !== 'undefined') {
    window.logger = logger;
    
    // 提供便利的調試命令（僅在開發模式）
    if (logger.getDebugMode()) {
        window.enableDebug = () => logger.setDebugMode(true);
        window.disableDebug = () => logger.setDebugMode(false);
        window.getLogHistory = () => logger.getHistory();
        window.clearLogHistory = () => logger.clearHistory();
        window.getEnvInfo = () => logger.getEnvironmentInfo();
        
        console.log('[Logger] Debug commands available: enableDebug(), disableDebug(), getLogHistory(), clearLogHistory(), getEnvInfo()');
    }
}
