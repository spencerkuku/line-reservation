<template>
  <div class="action-buttons">
    <slot></slot>
  </div>
</template>

<script setup>
// 統一的操作按鈕容器
// 提供一致的按鈕間距和佈局
</script>

<style scoped>
.action-buttons {
  display: flex;
  gap: 8px;
  justify-content: center;
  align-items: center;
  flex-wrap: wrap;
}

.action-buttons :deep(.el-button) {
  margin: 0;
  min-width: 60px;
}

/* 按鈕大小統一為 small */
.action-buttons :deep(.el-button--small) {
  padding: 5px 11px;
  font-size: 12px;
}

/* 文字按鈕樣式 */
.action-buttons :deep(.el-button.is-link) {
  min-width: auto;
  padding: 5px 8px;
}
</style>
