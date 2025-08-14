/**
 * 日誌記錄組合式函數
 * 為 Vue 組件提供便捷的日誌記錄功能
 */

import { onMounted, onUnmounted, getCurrentInstance } from 'vue'
import logger from '../utils/logger.js'

export function useLogger() {
    const instance = getCurrentInstance()
    const componentName = instance?.type.name || instance?.type.__name || 'Anonymous'
    
    // 自動記錄組件生命週期
    onMounted(() => {
        logger.lifecycle(componentName, 'mounted')
    })
    
    onUnmounted(() => {
        logger.lifecycle(componentName, 'unmounted')
    })
    
    return {
        // 基本日誌方法
        logInfo: (message, data = {}) => {
            logger.info(message, { ...data, component: componentName })
        },
        
        logWarning: (message, data = {}) => {
            logger.warn(message, { ...data, component: componentName })
        },
        
        logError: (message, error = null, data = {}) => {
            logger.error(message, error, { ...data, component: componentName })
        },
        
        // 用戶操作記錄
        logUserAction: (action, data = {}) => {
            logger.userAction(action, { 
                ...data, 
                component: componentName,
                timestamp: new Date().toISOString()
            })
        },
        
        // 性能記錄
        logPerformance: (operation, startTime, data = {}) => {
            logger.performance(operation, startTime, { 
                ...data, 
                component: componentName 
            })
        },
        
        // 表單提交記錄
        logFormSubmit: (formName, data = {}) => {
            logger.userAction('form_submit', {
                form_name: formName,
                ...data,
                component: componentName
            })
        },
        
        // 按鈕點擊記錄
        logButtonClick: (buttonName, data = {}) => {
            logger.userAction('button_click', {
                button_name: buttonName,
                ...data,
                component: componentName
            })
        },
        
        // 頁面互動記錄
        logInteraction: (interactionType, target, data = {}) => {
            logger.userAction('user_interaction', {
                interaction_type: interactionType,
                target,
                ...data,
                component: componentName
            })
        },
        
        // 數據載入記錄
        logDataLoad: (dataType, success = true, data = {}) => {
            const message = success ? `Data loaded: ${dataType}` : `Data load failed: ${dataType}`;
            const method = success ? 'info' : 'error';
            
            logger[method](message, {
                data_type: dataType,
                success,
                ...data,
                component: componentName
            })
        },
        
        // API 請求記錄（用於組件級別的特殊處理）
        logApiCall: (method, endpoint, requestData = null, success = true, responseData = null) => {
            const message = `${componentName} API call: ${method} ${endpoint}`;
            
            logger.info(message, {
                method,
                endpoint,
                request_data: requestData,
                success,
                response_data: responseData,
                component: componentName
            })
        },
        
        // 錯誤邊界記錄
        logComponentError: (error, errorInfo = {}) => {
            logger.error(`Component error in ${componentName}`, error, {
                error_info: errorInfo,
                component: componentName
            })
        },
        
        // 直接存取 logger 實例
        logger
    }
}

/**
 * 性能監控組合式函數
 */
export function usePerformanceLogger() {
    const { logPerformance } = useLogger()
    
    const startTimer = (operation) => {
        const startTime = performance.now()
        
        return {
            end: (data = {}) => {
                logPerformance(operation, startTime, data)
                return performance.now() - startTime
            }
        }
    }
    
    const measureAsync = async (operation, asyncFn, data = {}) => {
        const timer = startTimer(operation)
        try {
            const result = await asyncFn()
            timer.end({ ...data, success: true })
            return result
        } catch (error) {
            timer.end({ ...data, success: false, error: error.message })
            throw error
        }
    }
    
    const measureSync = (operation, syncFn, data = {}) => {
        const timer = startTimer(operation)
        try {
            const result = syncFn()
            timer.end({ ...data, success: true })
            return result
        } catch (error) {
            timer.end({ ...data, success: false, error: error.message })
            throw error
        }
    }
    
    return {
        startTimer,
        measureAsync,
        measureSync
    }
}

/**
 * 用戶行為追蹤組合式函數
 */
export function useUserTracking() {
    const { logUserAction } = useLogger()
    
    const trackPageView = (pageName, additionalData = {}) => {
        logger.userAction('page_view', { page_name: pageName, ...additionalData })
    }
    
    const trackClick = (elementType, elementId, additionalData = {}) => {
        logger.userAction('click', {
            element_type: elementType,
            element_id: elementId,
            ...additionalData
        })
    }
    
    const trackFormInteraction = (formId, action, field = null, additionalData = {}) => {
        logger.userAction('form_interaction', {
            form_id: formId,
            action, // 'focus', 'blur', 'change', 'submit', etc.
            field,
            ...additionalData
        })
    }
    
    const trackSearch = (query, filters = {}, results = null) => {
        logger.userAction('search', {
            query,
            filters,
            results_count: results?.length || null
        })
    }
    
    const trackDownload = (fileName, fileType, fileSize = null) => {
        logger.userAction('download', {
            file_name: fileName,
            file_type: fileType,
            file_size: fileSize
        })
    }
    
    return {
        trackPageView,
        trackClick,
        trackFormInteraction,
        trackSearch,
        trackDownload
    }
}
