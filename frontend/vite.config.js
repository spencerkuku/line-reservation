import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  server: {
    host: '0.0.0.0', // 讓外部設備能透過 ngrok 存取
    port: 5173,
    allowedHosts: ['.ngrok-free.app'], // 接受所有 ngrok 子網域（更通用）
    cors: {
      origin: ['http://localhost:8000', 'http://127.0.0.1:8000'],
      credentials: true,
    },
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000', // 本機後端
        changeOrigin: true,
        secure: false,
        ws: true,
        configure: (proxy, _options) => {
          proxy.on('error', (err, _req, _res) => {
            console.error('proxy error', err);
          });
          proxy.on('proxyReq', (proxyReq, req, _res) => {
            console.log('Sending Request to Target:', req.method, req.url);
          });
          proxy.on('proxyRes', (proxyRes, req, _res) => {
            console.log('Received Response from Target:', proxyRes.statusCode, req.url);
          });
        },
      },
    },
  },
  build: {
    outDir: 'dist',
    sourcemap: false, // 生產環境不生成 sourcemap（安全考量）
    minify: 'terser',
    terserOptions: {
      compress: {
        drop_console: true, // 移除 console.log
        drop_debugger: true, // 移除 debugger
      },
    },
  },
  define: {
    global: 'globalThis',
  },
})
