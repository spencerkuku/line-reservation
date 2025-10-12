import { ref, computed } from 'vue'

/**
 * 預約記錄篩選邏輯
 * 提供狀態和時間雙層篩選功能
 */
export function useReservationFilter(reservations) {
  // 當前選中的狀態篩選
  const activeStatus = ref('all')
  
  // 當前選中的時間篩選
  const activePeriod = ref('today')
  
  // 進階搜尋表單
  const advancedSearch = ref({
    customerName: '',
    phone: '',
    notes: '',
  })
  
  // 是否顯示進階搜尋
  const showAdvancedSearch = ref(false)
  
  /**
   * 狀態篩選選項
   */
  const statusTabs = computed(() => {
    const allReservations = reservations.value || []
    
    return [
      {
        value: 'all',
        label: '全部',
        count: allReservations.length,
        color: 'gray',
      },
      {
        value: 'pending',
        label: '待確認',
        count: allReservations.filter(r => r.status === 'pending').length,
        color: 'yellow',
      },
      {
        value: 'confirmed',
        label: '已確認',
        count: allReservations.filter(r => r.status === 'confirmed').length,
        color: 'blue',
      },
      {
        value: 'completed',
        label: '已完成',
        count: allReservations.filter(r => r.status === 'completed').length,
        color: 'green',
      },
      {
        value: 'cancelled',
        label: '已取消',
        count: allReservations.filter(r => r.status === 'cancelled').length,
        color: 'red',
      },
    ]
  })
  
  /**
   * 時間篩選選項
   */
  const timePeriods = computed(() => {
    const today = new Date()
    const tomorrow = new Date(today)
    tomorrow.setDate(tomorrow.getDate() + 1)
    
    return [
      {
        value: 'today',
        label: '今天',
        date: formatDate(today),
      },
      {
        value: 'tomorrow',
        label: '明天',
        date: formatDate(tomorrow),
      },
      {
        value: 'upcoming',
        label: '未來 7 天',
        date: null,
      },
      {
        value: 'all',
        label: '全部時間',
        date: null,
      },
    ]
  })
  
  /**
   * 過濾後的預約列表
   */
  const filteredReservations = computed(() => {
    let result = reservations.value || []
    
    // 1. 狀態篩選
    if (activeStatus.value !== 'all') {
      result = result.filter(r => r.status === activeStatus.value)
    }
    
    // 2. 時間篩選
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    
    switch (activePeriod.value) {
      case 'today':
        result = result.filter(r => isSameDay(r.reservation_date || r.time, today))
        break
        
      case 'tomorrow':
        const tomorrow = new Date(today)
        tomorrow.setDate(tomorrow.getDate() + 1)
        result = result.filter(r => isSameDay(r.reservation_date || r.time, tomorrow))
        break
        
      case 'upcoming':
        const nextWeek = new Date(today)
        nextWeek.setDate(nextWeek.getDate() + 7)
        result = result.filter(r => {
          const resDate = new Date(r.reservation_date || r.time)
          resDate.setHours(0, 0, 0, 0)
          return resDate >= today && resDate <= nextWeek
        })
        break
        
      case 'all':
        // 不進行時間篩選
        break
    }
    
    // 3. 進階搜尋
    if (advancedSearch.value.customerName) {
      const keyword = advancedSearch.value.customerName.toLowerCase()
      result = result.filter(r =>
        (r.customer_name || '').toLowerCase().includes(keyword) ||
        (r.reservation_name || '').toLowerCase().includes(keyword) ||
        (r.customer?.name || '').toLowerCase().includes(keyword) ||
        (r.customer?.line_display_name || '').toLowerCase().includes(keyword)
      )
    }
    
    if (advancedSearch.value.phone) {
      const keyword = advancedSearch.value.phone
      result = result.filter(r =>
        (r.customer_phone || '').includes(keyword) ||
        (r.reservation_phone || '').includes(keyword)
      )
    }
    
    if (advancedSearch.value.notes) {
      const keyword = advancedSearch.value.notes.toLowerCase()
      result = result.filter(r =>
        (r.reservation_notes || '').toLowerCase().includes(keyword)
      )
    }
    
    // 4. 排序：pending 優先，然後按時間
    return result.sort((a, b) => {
      const now = new Date()
      const dateA = new Date(a.reservation_date || a.time)
      const dateB = new Date(b.reservation_date || b.time)
      
      // pending 最優先
      if (a.status === 'pending' && b.status !== 'pending') return -1
      if (b.status === 'pending' && a.status !== 'pending') return 1
      
      // 都不是 pending，接下來比時間
      const aFuture = dateA > now
      const bFuture = dateB > now
      
      // 未來的預約排前面，按時間升序
      if (aFuture && bFuture) return dateA - dateB
      // 過期的預約排後面，按時間降序
      if (!aFuture && !bFuture) return dateB - dateA
      // 一個未來一個過期 → 未來排前
      if (aFuture && !bFuture) return -1
      if (!aFuture && bFuture) return 1
      
      return 0
    })
  })
  
  /**
   * 統計數據
   */
  const statistics = computed(() => {
    const all = reservations.value || []
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    
    const todayReservations = all.filter(r => isSameDay(r.reservation_date || r.time, today))
    
    return {
      total: all.length,
      today: todayReservations.length,
      pending: all.filter(r => r.status === 'pending').length,
      confirmed: all.filter(r => r.status === 'confirmed').length,
      completed: all.filter(r => r.status === 'completed').length,
      noShow: all.filter(r => r.check_in_status === 'no_show' || r.no_show).length,
    }
  })
  
  /**
   * 重置篩選條件
   */
  const resetFilters = () => {
    activeStatus.value = 'all'
    activePeriod.value = 'today'
    advancedSearch.value = {
      customerName: '',
      phone: '',
      notes: '',
    }
    showAdvancedSearch.value = false
  }
  
  /**
   * 重置進階搜尋
   */
  const resetAdvancedSearch = () => {
    advancedSearch.value = {
      customerName: '',
      phone: '',
      notes: '',
    }
  }
  
  return {
    // 狀態
    activeStatus,
    activePeriod,
    advancedSearch,
    showAdvancedSearch,
    
    // 計算屬性
    statusTabs,
    timePeriods,
    filteredReservations,
    statistics,
    
    // 方法
    resetFilters,
    resetAdvancedSearch,
  }
}

/**
 * 輔助函數：格式化日期
 */
function formatDate(date) {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${month}/${day}`
}

/**
 * 輔助函數：判斷是否同一天
 */
function isSameDay(dateStr, targetDate) {
  if (!dateStr) return false
  
  const date = new Date(dateStr)
  date.setHours(0, 0, 0, 0)
  
  const target = new Date(targetDate)
  target.setHours(0, 0, 0, 0)
  
  return date.getTime() === target.getTime()
}
