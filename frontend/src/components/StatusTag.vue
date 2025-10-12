<template>
  <el-tag :type="tagType" :effect="effect" :size="size">
    {{ tagText }}
  </el-tag>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: {
    type: String,
    required: true
  },
  type: {
    type: String,
    default: 'reservation' // reservation, checkin, payment, user, service
  },
  effect: {
    type: String,
    default: 'light' // light, dark, plain
  },
  size: {
    type: String,
    default: 'default' // large, default, small
  }
})

// 統一的狀態配置
const statusConfig = {
  // 預約狀態
  reservation: {
    pending: { type: 'warning', text: '待確認' },
    confirmed: { type: 'success', text: '已確認' },
    completed: { type: 'info', text: '已完成' },
    cancelled: { type: 'danger', text: '已取消' },
    no_show: { type: 'danger', text: '未到場' }
  },
  // 報到狀態
  checkin: {
    pending: { type: 'warning', text: '待報到' },
    checked_in: { type: 'success', text: '已報到' },
    completed: { type: 'info', text: '已完成' },
    cancelled: { type: 'danger', text: '已取消' },
    no_show: { type: 'danger', text: '未到場' }
  },
  // 付款狀態
  payment: {
    unpaid: { type: 'warning', text: '未付款' },
    partial: { type: 'warning', text: '部分付款' },
    paid: { type: 'success', text: '已付款' },
    refunded: { type: 'info', text: '已退款' }
  },
  // 用戶狀態
  user: {
    active: { type: 'success', text: '啟用' },
    inactive: { type: 'danger', text: '停用' }
  },
  // 服務狀態
  service: {
    active: { type: 'success', text: '啟用' },
    inactive: { type: 'info', text: '停用' }
  },
  // 客戶狀態
  customer: {
    active: { type: 'success', text: '正常' },
    blocked: { type: 'danger', text: '封鎖' }
  }
}

const tagType = computed(() => {
  return statusConfig[props.type]?.[props.status]?.type || 'info'
})

const tagText = computed(() => {
  return statusConfig[props.type]?.[props.status]?.text || props.status
})
</script>

<style scoped>
/* 可以添加自定義樣式 */
</style>
