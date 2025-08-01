/**
 * 增強的身份驗證服務
 * 符合 OWASP A07:2021 建議
 */

import { SecureStorage } from './security.js'
import logger from './logger.js'

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api'

class AuthenticationService {
    constructor() {
        this.maxLoginAttempts = 5
        this.lockoutDuration = 15 * 60 * 1000 // 15 分鐘
        this.sessionTimeout = 8 * 60 * 60 * 1000 // 8 小時
        this.tokenRefreshThreshold = 30 * 60 * 1000 // 30 分鐘
    }

    /**
     * 檢查登入嘗試次數
     */
    checkLoginAttempts(email) {
        const attempts = SecureStorage.getItem(`login_attempts_${email}`, false) || { count: 0, timestamp: Date.now() }
        const now = Date.now()

        // 如果超過鎖定時間，重置嘗試次數
        if (now - attempts.timestamp > this.lockoutDuration) {
            attempts.count = 0
            attempts.timestamp = now
        }

        return {
            count: attempts.count,
            isLocked: attempts.count >= this.maxLoginAttempts,
            remainingTime: Math.max(0, this.lockoutDuration - (now - attempts.timestamp))
        }
    }

    /**
     * 記錄登入嘗試
     */
    recordLoginAttempt(email, success = false) {
        const key = `login_attempts_${email}`
        
        if (success) {
            SecureStorage.removeItem(key)
        } else {
            const attempts = SecureStorage.getItem(key, false) || { count: 0, timestamp: Date.now() }
            attempts.count += 1
            attempts.timestamp = Date.now()
            SecureStorage.setItem(key, attempts, false)

            logger.logWarning('Failed login attempt', {
                email: this.hashEmail(email),
                attempts: attempts.count,
                ip: this.getClientIP()
            })
        }
    }

    /**
     * 驗證密碼強度
     */
    validatePasswordStrength(password) {
        const minLength = 8
        const errors = []

        if (password.length < minLength) {
            errors.push(`密碼長度至少需要 ${minLength} 個字符`)
        }

        if (!/[a-z]/.test(password)) {
            errors.push('密碼需要包含小寫字母')
        }

        if (!/[A-Z]/.test(password)) {
            errors.push('密碼需要包含大寫字母')
        }

        if (!/\d/.test(password)) {
            errors.push('密碼需要包含數字')
        }

        if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
            errors.push('密碼需要包含特殊字符')
        }

        // 檢查常見弱密碼
        const commonPasswords = [
            'password', '123456', '123456789', 'qwerty', 'abc123',
            'password123', 'admin', '12345678', 'welcome', 'login'
        ]

        if (commonPasswords.includes(password.toLowerCase())) {
            errors.push('請避免使用常見的弱密碼')
        }

        return {
            isValid: errors.length === 0,
            errors,
            strength: this.calculatePasswordStrength(password)
        }
    }

    /**
     * 計算密碼強度分數
     */
    calculatePasswordStrength(password) {
        let score = 0

        // 長度分數
        score += Math.min(password.length * 4, 20)

        // 字符類型分數
        if (/[a-z]/.test(password)) score += 5
        if (/[A-Z]/.test(password)) score += 5
        if (/\d/.test(password)) score += 5
        if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) score += 10

        // 複雜度分數
        const uniqueChars = [...new Set(password)].length
        score += uniqueChars * 2

        return Math.min(score, 100)
    }

    /**
     * 檢查 token 有效性
     */
    async validateToken(token) {
        if (!token) return false

        try {
            // 檢查 token 格式
            if (!this.isValidTokenFormat(token)) {
                return false
            }

            // 檢查 token 是否過期
            const tokenData = SecureStorage.getItem('token_metadata')
            if (tokenData && this.isTokenExpired(tokenData)) {
                this.clearAuthData()
                return false
            }

            // 向服務器驗證 token
            const response = await fetch(`${API_BASE_URL}/auth/user`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json'
                }
            })

            if (!response.ok) {
                throw new Error('Token validation failed')
            }

            const data = await response.json()
            
            // 更新用戶資料
            if (data.user) {
                SecureStorage.setItem('user', data.user)
                this.updateTokenMetadata()
            }

            return true

        } catch (error) {
            logger.logError('Token validation failed', error)
            this.clearAuthData()
            return false
        }
    }

    /**
     * 檢查 token 格式
     */
    isValidTokenFormat(token) {
        // Laravel Sanctum token 格式檢查
        return typeof token === 'string' && 
               token.length >= 40 && 
               token.length <= 255 &&
               /^[a-zA-Z0-9]+$/.test(token)
    }

    /**
     * 檢查 token 是否過期
     */
    isTokenExpired(tokenData) {
        if (!tokenData || !tokenData.timestamp) return true
        
        const now = Date.now()
        const age = now - tokenData.timestamp
        
        return age > this.sessionTimeout
    }

    /**
     * 更新 token 元數據
     */
    updateTokenMetadata() {
        SecureStorage.setItem('token_metadata', {
            timestamp: Date.now(),
            lastValidated: Date.now()
        })
    }

    /**
     * 清除認證數據
     */
    clearAuthData() {
        const keysToRemove = ['token', 'user', 'token_metadata']
        keysToRemove.forEach(key => SecureStorage.removeItem(key))
    }

    /**
     * 安全登出
     */
    async logout() {
        try {
            const token = SecureStorage.getItem('token')
            
            if (token) {
                // 通知服務器登出
                await fetch(`${API_BASE_URL}/auth/logout`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                })
            }
        } catch (error) {
            logger.logError('Logout request failed', error)
        } finally {
            this.clearAuthData()
        }
    }

    /**
     * 雜湊 email 用於日誌記錄
     */
    hashEmail(email) {
        if (!email) return ''
        return btoa(email).substring(0, 8) + '***'
    }

    /**
     * 獲取客戶端 IP（如果可用）
     */
    getClientIP() {
        // 前端無法直接獲取真實 IP，這裡返回佔位符
        return 'client'
    }

    /**
     * 設定會話過期警告
     */
    setupSessionWarning() {
        const warningTime = this.sessionTimeout - (10 * 60 * 1000) // 過期前 10 分鐘警告
        
        setTimeout(() => {
            if (SecureStorage.getItem('token')) {
                this.showSessionWarning()
            }
        }, warningTime)
    }

    /**
     * 顯示會話過期警告
     */
    showSessionWarning() {
        const shouldExtend = confirm('您的會話即將過期，是否要延長會話時間？')
        
        if (shouldExtend) {
            this.refreshSession()
        } else {
            this.logout()
        }
    }

    /**
     * 刷新會話
     */
    async refreshSession() {
        const token = SecureStorage.getItem('token')
        
        if (token && await this.validateToken(token)) {
            this.updateTokenMetadata()
            this.setupSessionWarning()
        } else {
            this.logout()
        }
    }
}

export default new AuthenticationService()
