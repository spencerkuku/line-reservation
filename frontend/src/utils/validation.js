/**
 * 前端表單驗證工具
 * 提供統一的客戶端驗證邏輯
 */

// 驗證規則
export const ValidationRules = {
  // 必填
  required: (value, fieldName = '此欄位') => {
    if (!value || (typeof value === 'string' && !value.trim())) {
      return `${fieldName}為必填項目`
    }
    return null
  },

  // 最大長度
  maxLength: (value, max, fieldName = '此欄位') => {
    if (value && value.length > max) {
      return `${fieldName}不得超過${max}個字符`
    }
    return null
  },

  // 電子信箱格式
  email: (value, fieldName = '電子信箱') => {
    if (!value) return null
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!emailRegex.test(value)) {
      return `請輸入有效的${fieldName}格式`
    }
    return null
  },

  // 電話號碼格式（台灣）
  phone: (value, fieldName = '電話號碼') => {
    if (!value) return null
    const phoneRegex = /^(\+886|0)([0-9]{8,9})$/
    if (!phoneRegex.test(value.replace(/[\s-]/g, ''))) {
      return `請輸入有效的${fieldName}格式`
    }
    return null
  },

  // 日期格式且早於今天
  birthdayDate: (value, fieldName = '生日') => {
    if (!value) return null
    const date = new Date(value)
    const today = new Date()
    
    if (isNaN(date.getTime())) {
      return `請輸入有效的${fieldName}日期`
    }
    
    if (date >= today) {
      return `${fieldName}必須早於今天`
    }
    
    return null
  },

  // 姓名格式（中文、英文、數字、空格）
  name: (value, fieldName = '姓名') => {
    if (!value) return null
    const nameRegex = /^[\u4e00-\u9fa5a-zA-Z0-9\s]+$/
    if (!nameRegex.test(value)) {
      return `${fieldName}只能包含中文、英文、數字和空格`
    }
    return null
  },

  // 性別選項
  gender: (value, fieldName = '性別') => {
    if (!value) return null
    const validGenders = ['male', 'female', 'other']
    if (!validGenders.includes(value)) {
      return `請選擇有效的${fieldName}`
    }
    return null
  }
}

// 客戶表單驗證器
export class CustomerValidator {
  static validateCustomerForm(form) {
    const errors = {}

    // 驗證姓名
    const nameError = ValidationRules.required(form.name, '客戶姓名') ||
                     ValidationRules.maxLength(form.name, 255, '客戶姓名') ||
                     ValidationRules.name(form.name, '客戶姓名')
    if (nameError) errors.name = nameError

    // 驗證 LINE 用戶 ID
    const lineUserIdError = ValidationRules.maxLength(form.line_user_id, 100, 'LINE用戶ID')
    if (lineUserIdError) errors.line_user_id = lineUserIdError

    // 驗證電話
    const phoneError = ValidationRules.maxLength(form.phone, 20, '電話號碼') ||
                      ValidationRules.phone(form.phone, '電話號碼')
    if (phoneError) errors.phone = phoneError

    // 驗證電子信箱
    const emailError = ValidationRules.maxLength(form.email, 255, '電子信箱') ||
                      ValidationRules.email(form.email, '電子信箱')
    if (emailError) errors.email = emailError

    // 驗證性別
    const genderError = ValidationRules.gender(form.gender, '性別')
    if (genderError) errors.gender = genderError

    // 驗證生日
    const birthdayError = ValidationRules.birthdayDate(form.birthday, '生日')
    if (birthdayError) errors.birthday = birthdayError

    // 驗證地址
    const addressError = ValidationRules.maxLength(form.address, 500, '地址')
    if (addressError) errors.address = addressError

    // 驗證備註
    const notesError = ValidationRules.maxLength(form.notes, 1000, '備註')
    if (notesError) errors.notes = notesError

    // 驗證推薦來源
    const referralSourceError = ValidationRules.maxLength(form.referral_source, 255, '推薦來源')
    if (referralSourceError) errors.referral_source = referralSourceError

    return {
      isValid: Object.keys(errors).length === 0,
      errors
    }
  }

  // 即時驗證單一欄位
  static validateField(fieldName, value, form = {}) {
    const tempForm = { ...form, [fieldName]: value }
    const validation = this.validateCustomerForm(tempForm)
    return validation.errors[fieldName] || null
  }
}

// 服務表單驗證器
export class ServiceValidator {
  static validateServiceForm(form) {
    const errors = {}

    // 驗證服務名稱
    const nameError = ValidationRules.required(form.name, '服務名稱') ||
                     ValidationRules.maxLength(form.name, 255, '服務名稱')
    if (nameError) errors.name = nameError

    // 驗證描述
    const descriptionError = ValidationRules.maxLength(form.description, 1000, '服務描述')
    if (descriptionError) errors.description = descriptionError

    // 驗證價格
    if (form.price !== undefined && form.price !== null) {
      if (isNaN(form.price) || form.price < 0) {
        errors.price = '價格必須為非負數'
      }
    }

    // 驗證持續時間
    if (form.duration !== undefined && form.duration !== null) {
      if (isNaN(form.duration) || form.duration <= 0) {
        errors.duration = '持續時間必須為正數'
      }
    }

    return {
      isValid: Object.keys(errors).length === 0,
      errors
    }
  }
}

// 可用時段驗證器
export class AvailableTimeValidator {
  static validateAvailableTimeForm(form) {
    const errors = {}

    // 驗證開始時間
    if (!form.start_time) {
      errors.start_time = '開始時間為必填項目'
    }

    // 驗證結束時間
    if (!form.end_time) {
      errors.end_time = '結束時間為必填項目'
    }

    // 驗證時間邏輯
    if (form.start_time && form.end_time) {
      const startTime = new Date(`2000-01-01 ${form.start_time}`)
      const endTime = new Date(`2000-01-01 ${form.end_time}`)
      
      if (startTime >= endTime) {
        errors.end_time = '結束時間必須晚於開始時間'
      }
    }

    // 驗證最大容量
    if (form.max_capacity !== undefined && form.max_capacity !== null) {
      if (isNaN(form.max_capacity) || form.max_capacity <= 0) {
        errors.max_capacity = '最大容量必須為正數'
      }
    }

    return {
      isValid: Object.keys(errors).length === 0,
      errors
    }
  }
}

// 用戶驗證器
export class UserValidator {
  static validateUserForm(form) {
    const errors = {}

    // 驗證姓名
    const nameError = ValidationRules.required(form.name, '用戶名稱') ||
                     ValidationRules.maxLength(form.name, 255, '用戶名稱')
    if (nameError) errors.name = nameError

    // 驗證電子信箱
    const emailError = ValidationRules.required(form.email, '電子信箱') ||
                      ValidationRules.maxLength(form.email, 255, '電子信箱') ||
                      ValidationRules.email(form.email, '電子信箱')
    if (emailError) errors.email = emailError

    // 驗證密碼（僅在新增或修改密碼時）
    if (form.password !== undefined) {
      if (!form.password) {
        errors.password = '密碼為必填項目'
      } else if (form.password.length < 8) {
        errors.password = '密碼長度至少需要8個字符'
      }
    }

    // 驗證角色
    if (form.role) {
      const validRoles = ['admin', 'user']
      if (!validRoles.includes(form.role)) {
        errors.role = '請選擇有效的角色'
      }
    }

    return {
      isValid: Object.keys(errors).length === 0,
      errors
    }
  }
}

// 登入表單驗證器
export class LoginValidator {
  static validateLoginForm(form) {
    const errors = {}

    // 驗證電子信箱
    const emailError = ValidationRules.required(form.email, '電子信箱') ||
                      ValidationRules.email(form.email, '電子信箱')
    if (emailError) errors.email = emailError

    // 驗證密碼
    const passwordError = ValidationRules.required(form.password, '密碼')
    if (passwordError) errors.password = passwordError

    return {
      isValid: Object.keys(errors).length === 0,
      errors
    }
  }
}

// 通用驗證工具
export const ValidationUtils = {
  // 清理輸入（移除前後空白）
  sanitizeInput: (value) => {
    if (typeof value === 'string') {
      return value.trim()
    }
    return value
  },

  // 格式化電話號碼
  formatPhone: (phone) => {
    if (!phone) return ''
    return phone.replace(/[\s-]/g, '')
  },

  // 檢查是否為有效日期
  isValidDate: (dateString) => {
    const date = new Date(dateString)
    return !isNaN(date.getTime())
  },

  // 顯示驗證錯誤
  displayErrors: (errors) => {
    const errorMessages = Object.values(errors).filter(Boolean)
    if (errorMessages.length > 0) {
      alert('表單驗證錯誤:\n' + errorMessages.join('\n'))
      return false
    }
    return true
  }
}
