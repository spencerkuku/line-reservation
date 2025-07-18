/**
 * 全局日誌工具
 * 在生產環境中自動禁用除錯訊息，在開發環境中保留完整日誌
 */

const isDev = import.meta.env.DEV
const isProd = import.meta.env.PROD

class Logger {
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
    // 錯誤在所有環境都要記錄
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

  // 生產環境錯誤追蹤（可擴展為發送到錯誤追蹤服務）
  static trackError(error, context = {}) {
    if (isProd) {
      // 在生產環境中，你可以將錯誤發送到錯誤追蹤服務
      // 例如：Sentry, LogRocket, Bugsnag 等
      // 這裡只是記錄錯誤，實際實現時可以替換為你選擇的服務
      const errorData = {
        message: error.message,
        stack: error.stack,
        timestamp: new Date().toISOString(),
        userAgent: navigator.userAgent,
        url: window.location.href,
        context
      }
      
      // 發送到錯誤追蹤服務的代碼
      // trackingService.captureException(errorData)
      
      console.error('Production Error:', errorData)
    } else {
      console.error('Dev Error:', error, context)
    }
  }

  // API 請求日誌
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
    
    // 在生產環境也要記錄 API 錯誤
    if (isProd) {
      this.trackError(error, { method, url, type: 'API_ERROR' })
    }
  }
}

export default Logger
