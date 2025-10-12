<template>
  <div class="data-table-container">
    <!-- 統一的搜尋列 -->
    <el-card shadow="never" class="search-card" v-if="$slots['search-fields']">
      <el-row :gutter="20">
        <el-col :span="hasActionButton ? 18 : 24">
          <el-form :inline="true" :model="searchForm" @submit.prevent="handleSearch">
            <slot name="search-fields"></slot>
            <el-form-item>
              <el-button type="primary" @click="handleSearch" :loading="loading">
                <el-icon><Search /></el-icon>
                搜尋
              </el-button>
              <el-button @click="handleReset">
                <el-icon><Refresh /></el-icon>
                重置
              </el-button>
            </el-form-item>
          </el-form>
        </el-col>
        <el-col v-if="hasActionButton" :span="6" class="text-right">
          <slot name="action-button"></slot>
        </el-col>
      </el-row>
    </el-card>

    <!-- 統一的表格 -->
    <el-card shadow="never" class="table-card">
      <template #header v-if="title">
        <div class="card-header">
          <span class="card-title">{{ title }}</span>
          <div class="card-extra">
            <slot name="header-extra"></slot>
          </div>
        </div>
      </template>

      <el-table
        :data="tableData"
        :loading="loading"
        stripe
        style="width: 100%"
        v-bind="$attrs"
      >
        <slot name="columns"></slot>
        
        <!-- 統一的操作列 -->
        <el-table-column 
          v-if="$slots.actions"
          label="操作" 
          :width="actionColumnWidth" 
          fixed="right"
          align="center"
        >
          <template #default="scope">
            <div class="action-buttons">
              <slot name="actions" :row="scope.row" :index="scope.$index"></slot>
            </div>
          </template>
        </el-table-column>
      </el-table>

      <!-- 統一的分頁 -->
      <div class="pagination-container" v-if="showPagination">
        <el-pagination
          v-model:current-page="currentPageModel"
          v-model:page-size="pageSizeModel"
          :page-sizes="pageSizes"
          :total="total"
          :layout="paginationLayout"
          background
          @size-change="handleSizeChange"
          @current-change="handlePageChange"
        />
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Search, Refresh } from '@element-plus/icons-vue'

const props = defineProps({
  // 表格資料
  tableData: {
    type: Array,
    default: () => []
  },
  // 載入狀態
  loading: {
    type: Boolean,
    default: false
  },
  // 搜尋表單
  searchForm: {
    type: Object,
    default: () => ({})
  },
  // 卡片標題
  title: {
    type: String,
    default: ''
  },
  // 當前頁碼
  currentPage: {
    type: Number,
    default: 1
  },
  // 每頁數量
  pageSize: {
    type: Number,
    default: 20
  },
  // 總數量
  total: {
    type: Number,
    default: 0
  },
  // 是否顯示分頁
  showPagination: {
    type: Boolean,
    default: true
  },
  // 分頁大小選項
  pageSizes: {
    type: Array,
    default: () => [10, 20, 50, 100]
  },
  // 分頁佈局
  paginationLayout: {
    type: String,
    default: 'total, sizes, prev, pager, next, jumper'
  },
  // 操作列寬度
  actionColumnWidth: {
    type: [String, Number],
    default: 200
  }
})

const emit = defineEmits(['search', 'reset', 'update:currentPage', 'update:pageSize'])

// 計算是否有操作按鈕插槽
const hasActionButton = computed(() => {
  return !!getCurrentInstance().slots['action-button']
})

// 雙向綁定當前頁
const currentPageModel = computed({
  get: () => props.currentPage,
  set: (val) => emit('update:currentPage', val)
})

// 雙向綁定每頁數量
const pageSizeModel = computed({
  get: () => props.pageSize,
  set: (val) => emit('update:pageSize', val)
})

const handleSearch = () => {
  emit('search')
}

const handleReset = () => {
  emit('reset')
}

const handlePageChange = (page) => {
  emit('update:currentPage', page)
  emit('search')
}

const handleSizeChange = (size) => {
  emit('update:pageSize', size)
  emit('update:currentPage', 1)
  emit('search')
}
</script>

<script>
import { getCurrentInstance } from 'vue'
</script>

<style scoped>
.data-table-container {
  padding: 20px;
}

.search-card {
  margin-bottom: 20px;
}

.search-card :deep(.el-form) {
  display: flex;
  flex-wrap: wrap;
  gap: 0;
}

.search-card :deep(.el-form-item) {
  margin-bottom: 10px;
  margin-right: 16px;
}

.table-card {
  background: #fff;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-title {
  font-size: 16px;
  font-weight: 600;
  color: #303133;
}

.card-extra {
  display: flex;
  gap: 10px;
}

.pagination-container {
  display: flex;
  justify-content: flex-end;
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid var(--el-border-color-lighter);
}

.text-right {
  text-align: right;
  display: flex;
  justify-content: flex-end;
  align-items: center;
}

.action-buttons {
  display: flex;
  gap: 8px;
  justify-content: center;
  align-items: center;
  flex-wrap: wrap;
}

.action-buttons :deep(.el-button) {
  margin: 0;
}
</style>
