// vite.config.js
import { defineConfig, loadEnv } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  return {
    plugins: [
      laravel({
        // ★ 管理画面( Vue / Inertia ) と 公開グラフ用の JS を並列投入
        input: [
          'resources/js/app.js',           // 管理画面側
          'resources/css/app.css',
          'resources/js/public-charts.js', // 公開(検証)用：素のJS想定
        ],
        refresh: true,
      }),
      vue({
        template: {
          transformAssetUrls: { base: null, includeAbsolute: false },
        },
      }),
    ],
    // Docker開発時のHMRホスト調整（本番ビルドには影響しません）
    server: {
      host: '0.0.0.0',
      hmr: { host: (env.APP_URL || '').replace(/^https?:\/\//, '') || 'localhost' },
    },
  }
})
